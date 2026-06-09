<?php

namespace parallelogram\imagevariants\console\controllers;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use parallelogram\imagevariants\Plugin;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Pre-generates the assigned variants for existing assets.
 *
 * A plain, long-running CLI sweep — no queue. Idempotent (existing transforms
 * are not re-encoded), so re-running resumes by filling only what is missing.
 *
 *   php craft image-variants/warm
 *   php craft image-variants/warm --field=preview
 *   php craft image-variants/warm --matrix=card --volume=product --limit=100
 *   php craft image-variants/warm --throttle=0.25
 */
class WarmController extends Controller
{
    public $defaultAction = 'index';

    /** @var string|null Limit to a single Asset field handle. */
    public ?string $field = null;

    /** @var string|null Limit to a single matrix handle. */
    public ?string $matrix = null;

    /** @var string|null Limit to a single volume handle. */
    public ?string $volume = null;

    /** @var int|null Cap the number of assets processed. */
    public ?int $limit = null;

    /** @var float Seconds to pause between assets, to cap load. */
    public float $throttle = 0;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['field', 'matrix', 'volume', 'limit', 'throttle']);
    }

    public function actionIndex(): int
    {
        $settings = Plugin::getInstance()->getSettings();
        $generator = Plugin::getInstance()->generator;

        $assignments = $settings->fieldAssignments;
        if ($this->field !== null) {
            $assignments = array_intersect_key($assignments, [$this->field => true]);
        }

        // assetId => set of matrix handles to generate (union across fields)
        $assetMatrices = [];

        foreach ($assignments as $fieldHandle => $matrixHandles) {
            if ($this->matrix !== null) {
                $matrixHandles = array_values(array_intersect($matrixHandles, [$this->matrix]));
            }
            if ($matrixHandles === []) {
                continue;
            }

            $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
            if ($field === null) {
                continue;
            }

            $targetIds = (new Query())
                ->select('targetId')
                ->from(Table::RELATIONS)
                ->where(['fieldId' => $field->id])
                ->distinct()
                ->column();

            foreach ($targetIds as $assetId) {
                $assetMatrices[(int)$assetId] = array_values(array_unique(array_merge(
                    $assetMatrices[(int)$assetId] ?? [],
                    $matrixHandles
                )));
            }
        }

        if ($assetMatrices === []) {
            $this->stdout("Nothing to warm.\n");
            return ExitCode::OK;
        }

        // Restrict to real image assets (+ optional volume), preserving the map.
        $query = Asset::find()->id(array_keys($assetMatrices))->kind('image');
        if ($this->volume !== null) {
            $query->volume($this->volume);
        }
        $validIds = array_map('intval', $query->ids());
        $assetMatrices = array_intersect_key($assetMatrices, array_flip($validIds));

        if ($this->limit !== null) {
            $assetMatrices = array_slice($assetMatrices, 0, $this->limit, true);
        }

        $total = count($assetMatrices);
        if ($total === 0) {
            $this->stdout("Nothing to warm.\n");
            return ExitCode::OK;
        }

        $this->stdout("Warming {$total} asset(s)...\n\n");

        $ids = array_keys($assetMatrices);
        $processed = 0;
        $variants = 0;
        $batchSize = 100;

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $chunk = array_slice($ids, $offset, $batchSize);
            $assets = Asset::find()->id($chunk)->kind('image')->indexBy('id')->all();

            foreach ($chunk as $assetId) {
                $asset = $assets[$assetId] ?? null;
                if ($asset === null) {
                    continue;
                }

                $handles = $assetMatrices[$assetId];
                $made = $generator->generate($asset, $handles);
                $processed++;
                $variants += $made;

                $this->stdout(sprintf(
                    "[%d/%d] %s — %d variants (%s)\n",
                    $processed,
                    $total,
                    $asset->filename,
                    $made,
                    implode(',', $handles)
                ));

                if ($this->throttle > 0) {
                    usleep((int)($this->throttle * 1_000_000));
                }
            }
        }

        $this->stdout(sprintf(
            "\nDone. %d assets processed, %d variants generated.\n",
            $processed,
            $variants
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }
}
