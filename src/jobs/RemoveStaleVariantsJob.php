<?php

declare(strict_types=1);

namespace parallelogram\images\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use Override;
use parallelogram\images\craft\Warming;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\Plugin;

/**
 * Removes the variants of an image whose file was replaced. The new file
 * gives different variant URLs, so the old files can't be asked for again;
 * nothing new is made here, the replacement's variants are made when first
 * requested, or by `p-images/warm`.
 *
 * Removing files that are already gone does nothing, so running this twice
 * is harmless.
 *
 * @api
 */
final class RemoveStaleVariantsJob extends BaseJob
{
    public int $assetId = 0;

    /**
     * @throws StorageFailedException When the variant filesystem can't be reached.
     */
    #[Override]
    public function execute($queue): void
    {
        $this->remove(Plugin::getInstance()?->warming(), Asset::find()->id($this->assetId)->status(null)->one());
    }

    #[Override]
    protected function defaultDescription(): ?string
    {
        return Craft::t('p-images', message: 'Removing outdated image variants');
    }

    /**
     * @throws StorageFailedException
     */
    private function remove(?Warming $warming, mixed $asset): void
    {
        if (null !== $warming && $asset instanceof Asset) {
            $warming->removeStale($asset);
        }
    }
}
