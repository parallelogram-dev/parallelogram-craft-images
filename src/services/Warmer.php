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
    private const ACTIVE_CACHE_KEY = 'image-variants.warming';
    private const STOP_CACHE_KEY = 'image-variants.warm-stop';

    /**
     * Request that any in-flight warm (CLI or queue) stop at its next tick.
     */
    public function requestStop(): void
    {
        Craft::$app->getCache()->set(self::STOP_CACHE_KEY, time(), 3600);
    }

    /**
     * Whether a stop was requested after the given warm started.
     */
    public function stopRequested(int $startedAt): bool
    {
        $ts = Craft::$app->getCache()->get(self::STOP_CACHE_KEY);

        return $ts !== false && $ts !== null && (int)$ts >= $startedAt;
    }

    /**
     * Record a warm scope as in-progress (shared cache, so the CP can reflect
     * it). TTL guards against a hard-killed worker leaving a stuck marker.
     *
     * @param array{field: ?string, matrix: ?string, volume: ?string} $scope
     */
    public function markActive(array $scope, int $total = 0): void
    {
        $active = $this->activeScopes();
        $active[$this->scopeKey($scope)] = $scope + [
            'processed' => 0,
            'total' => $total,
            'startedAt' => time(),
            'updatedAt' => time(),
        ];
        Craft::$app->getCache()->set(self::ACTIVE_CACHE_KEY, $active, 3600);
    }

    /**
     * Update the live progress of an in-flight warm scope.
     *
     * @param array{field: ?string, matrix: ?string, volume: ?string} $scope
     */
    public function markProgress(array $scope, int $processed): void
    {
        $active = $this->activeScopes();
        $key = $this->scopeKey($scope);

        if (isset($active[$key])) {
            $active[$key]['processed'] = $processed;
            $active[$key]['updatedAt'] = time();
            Craft::$app->getCache()->set(self::ACTIVE_CACHE_KEY, $active, 3600);
        }
    }

    /**
     * @param array{field: ?string, matrix: ?string, volume: ?string} $scope
     */
    public function markDone(array $scope): void
    {
        $active = $this->activeScopes();
        unset($active[$this->scopeKey($scope)]);
        Craft::$app->getCache()->set(self::ACTIVE_CACHE_KEY, $active, 3600);
    }

    /**
     * @return array<string, array{field: ?string, matrix: ?string, volume: ?string}>
     */
    public function activeScopes(): array
    {
        return Craft::$app->getCache()->get(self::ACTIVE_CACHE_KEY) ?: [];
    }

    /**
     * Whether a running warm covers this field/matrix (a null field/matrix in a
     * scope means "everything"/"every matrix of that field").
     */
    public function isActive(?string $field, ?string $matrix): bool
    {
        foreach ($this->activeScopes() as $scope) {
            $f = $scope['field'] ?? null;
            $m = $scope['matrix'] ?? null;

            if (($f === null || $f === $field) && ($m === null || $m === $matrix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{field: ?string, matrix: ?string, volume: ?string} $scope
     */
    private function scopeKey(array $scope): string
    {
        return ($scope['field'] ?? '*') . '|' . ($scope['matrix'] ?? '*') . '|' . ($scope['volume'] ?? '*');
    }

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
    public function warm(array $plan, array $scope = [], ?callable $onProgress = null, float $throttleSeconds = 0): int
    {
        $generator = Plugin::getInstance()->generator;

        $ids = array_keys($plan);
        $total = count($ids);
        $processed = 0;
        $variants = 0;
        $batchSize = 100;
        $startedAt = time();
        $lastTick = 0;
        $tracked = $scope !== [];

        if ($tracked) {
            $this->markActive($scope, $total);
        }

        try {
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

                    // Roughly every 2s: publish progress and honour a stop request.
                    $now = time();
                    if ($tracked && ($now - $lastTick >= 2 || $processed === $total)) {
                        $this->markProgress($scope, $processed);
                        $lastTick = $now;

                        if ($this->stopRequested($startedAt)) {
                            unset($assets);
                            return $variants;
                        }
                    }

                    if ($throttleSeconds > 0) {
                        usleep((int)($throttleSeconds * 1_000_000));
                    }
                }

                unset($assets);
                gc_collect_cycles();
            }
        } finally {
            if ($tracked) {
                $this->markDone($scope);
            }
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
