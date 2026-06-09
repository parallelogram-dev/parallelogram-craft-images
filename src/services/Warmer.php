<?php

namespace parallelogram\imagevariants\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use parallelogram\imagevariants\Plugin;
use yii\base\Component;

/**
 * Builds the warm "plan": which matrices to generate for which assets, derived
 * from the field assignments. Shared by the console command, the queue WarmJob,
 * and the CP utility so they can't disagree.
 */
class Warmer extends Component
{
    /**
     * @return array<int, string[]> assetId => matrix handles (union across fields)
     */
    public function plan(?string $field = null, ?string $matrix = null, ?string $volume = null): array
    {
        $settings = Plugin::getInstance()->getSettings();

        $assignments = $settings->fieldAssignments;
        if ($field !== null) {
            $assignments = array_intersect_key($assignments, [$field => true]);
        }

        $assetMatrices = [];

        foreach ($assignments as $fieldHandle => $matrixHandles) {
            if ($matrix !== null) {
                $matrixHandles = array_values(array_intersect($matrixHandles, [$matrix]));
            }
            if ($matrixHandles === []) {
                continue;
            }

            $assetField = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
            if ($assetField === null) {
                continue;
            }

            $targetIds = (new Query())
                ->select('targetId')
                ->from(Table::RELATIONS)
                ->where(['fieldId' => $assetField->id])
                ->distinct()
                ->column();

            foreach ($targetIds as $targetId) {
                $targetId = (int)$targetId;
                $assetMatrices[$targetId] = array_values(array_unique(array_merge(
                    $assetMatrices[$targetId] ?? [],
                    $matrixHandles
                )));
            }
        }

        if ($assetMatrices === []) {
            return [];
        }

        $query = Asset::find()->id(array_keys($assetMatrices))->kind('image');
        if ($volume !== null) {
            $query->volume($volume);
        }

        $validIds = array_map('intval', $query->ids());

        return array_intersect_key($assetMatrices, array_flip($validIds));
    }

    /**
     * Generate a plan, batched and memory-bounded. The same code path for the
     * console command and the queue job, so both behave identically: one serial
     * pass, ~100 assets in memory at a time, freed (with a GC sweep) per batch.
     *
     * $onProgress receives (processed, total, asset|null, variantsMade) per asset.
     *
     * @param array<int, string[]> $plan
     * @param float $throttleSeconds Pause between assets, to cap load.
     */
    public function warm(array $plan, ?callable $onProgress = null, float $throttleSeconds = 0): int
    {
        $generator = Plugin::getInstance()->generator;

        $ids = array_keys($plan);
        $total = count($ids);
        $processed = 0;
        $variants = 0;
        $batchSize = 100;

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $chunk = array_slice($ids, $offset, $batchSize);
            $assets = Asset::find()->id($chunk)->kind('image')->indexBy('id')->all();

            foreach ($chunk as $assetId) {
                $asset = $assets[$assetId] ?? null;
                $made = $asset !== null ? $generator->generate($asset, $plan[$assetId]) : 0;
                $processed++;
                $variants += $made;

                if ($onProgress !== null) {
                    $onProgress($processed, $total, $asset, $made);
                }

                if ($throttleSeconds > 0) {
                    usleep((int)($throttleSeconds * 1_000_000));
                }
            }

            unset($assets);
            gc_collect_cycles();
        }

        return $variants;
    }

    /**
     * Per field+matrix asset counts, for the utility summary.
     *
     * @return array<int, array{field: string, matrix: string, count: int}>
     */
    public function summary(): array
    {
        $rows = [];

        foreach (Plugin::getInstance()->getSettings()->fieldAssignments as $field => $matrices) {
            foreach ($matrices as $matrix) {
                $rows[] = [
                    'field' => $field,
                    'matrix' => $matrix,
                    'count' => count($this->plan($field, $matrix)),
                ];
            }
        }

        return $rows;
    }
}
