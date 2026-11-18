<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;
use Craft;
use craft\elements\Asset;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\warming\WarmResult;

use function count;
use function intval;

/**
 * Making, checking and rebuilding variants for many images at once, one
 * image at a time so memory stays flat however large the library is.
 *
 * @internal
 */
final readonly class BatchWarming
{
    public function __construct(
        private Warming $warming,
    ) {}

    private static function counted(?WarmResult $result): BatchResult
    {
        if (null === $result) {
            return new BatchResult(
                images: 1,
                skipped: 1,
            );
        }

        return new BatchResult(
            images: 1,
            files: $result,
        );
    }

    /**
     * Counts the files each image is missing, making nothing.
     *
     * @param list<int> $ids
     * @param (Closure(int, int): void)|null $progress Told how many images are done of how many.
     */
    public function audit(array $ids, ?string $sizeSet, ?Closure $progress = null): BatchResult
    {
        return $this->run($ids, $progress, fn(Asset $asset): BatchResult => $this->auditOne($asset, $sizeSet));
    }

    /**
     * The IDs of every image, or of those in one volume. Null when there's no
     * volume with that handle.
     *
     * @return list<int>|null
     */
    public function imageIds(?string $volumeHandle): ?array
    {
        $query = Asset::find()->kind(Asset::KIND_IMAGE)->status(null);

        if (null !== $volumeHandle) {
            $volume = Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle);

            if (null === $volume) {
                return null;
            }

            $query = $query->volumeId($volume->id);
        }

        $ids = [];

        foreach ($query->ids() as $id) {
            $ids[] = intval($id);
        }

        return $ids;
    }

    /**
     * Deletes every variant of each image and makes the assigned ones again.
     *
     * @param list<int> $ids
     * @param (Closure(int, int): void)|null $progress
     */
    public function rebuild(array $ids, ?Closure $progress = null): BatchResult
    {
        return $this->run($ids, $progress, function (Asset $asset): BatchResult {
            try {
                return self::counted($this->warming->rebuild($asset));
            } catch (StorageFailedException) {
                return new BatchResult(
                    images: 1,
                    files: new WarmResult(failed: 1),
                );
            }
        });
    }

    /**
     * Makes each image's missing variants.
     *
     * @param list<int> $ids
     * @param (Closure(int, int): void)|null $progress
     */
    public function warm(array $ids, ?string $sizeSet, ?Closure $progress = null): BatchResult
    {
        return $this->run(
            $ids,
            $progress,
            fn(Asset $asset): BatchResult => self::counted($this->warming->warmAssigned($asset, $sizeSet)),
        );
    }

    private function auditOne(Asset $asset, ?string $sizeSet): BatchResult
    {
        try {
            return new BatchResult(
                images: 1,
                missing: count($this->warming->missing($asset, $sizeSet)),
            );
        } catch (StorageFailedException) {
            return new BatchResult(
                images: 1,
                files: new WarmResult(failed: 1),
            );
        }
    }

    /**
     * @param list<int> $ids
     * @param (Closure(int, int): void)|null $progress
     * @param Closure(Asset): BatchResult $each
     */
    private function run(array $ids, ?Closure $progress, Closure $each): BatchResult
    {
        $total  = count($ids);
        $totals = new BatchResult();
        $done   = 0;

        foreach ($ids as $id) {
            $asset  = Craft::$app->getAssets()->getAssetById($id);
            $totals = $totals->plus(
                null === $asset
                    ? new BatchResult(
                        images: 1,
                        skipped: 1,
                    ) : $each($asset),
            );

            ++$done;

            if (null !== $progress) {
                $progress($done, $total);
            }
        }

        return $totals;
    }
}
