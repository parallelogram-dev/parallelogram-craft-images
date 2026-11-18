<?php

declare(strict_types=1);

namespace parallelogram\images\jobs;

use Craft;
use craft\queue\BaseJob;
use Override;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\Plugin;

/**
 * Removes every variant of an asset that's been deleted.
 *
 * @api
 */
final class DeleteVariantsJob extends BaseJob
{
    public string $assetUid = '';

    /**
     * @throws StorageFailedException When the variant filesystem can't be reached.
     */
    #[Override]
    public function execute($queue): void
    {
        Plugin::getInstance()?->warming()?->forget($this->assetUid);
    }

    #[Override]
    protected function defaultDescription(): ?string
    {
        return Craft::t('p-images', message: 'Removing image variants');
    }
}
