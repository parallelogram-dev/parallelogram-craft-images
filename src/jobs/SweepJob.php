<?php

declare(strict_types=1);

namespace parallelogram\images\jobs;

use Craft;
use craft\queue\BaseJob;
use Override;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\Plugin;

/**
 * Removes variants nothing can ask for any more: those of deleted images,
 * and files left behind by replaced images or changed size sets.
 *
 * @api
 */
final class SweepJob extends BaseJob
{
    /**
     * @throws StorageFailedException When the variant filesystem can't be reached.
     */
    #[Override]
    public function execute($queue): void
    {
        Plugin::getInstance()?->warming()?->sweep();
    }

    #[Override]
    protected function defaultDescription(): ?string
    {
        return Craft::t('p-images', message: 'Removing unused image variants');
    }
}
