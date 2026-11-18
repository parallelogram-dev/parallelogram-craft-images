<?php

declare(strict_types=1);

namespace parallelogram\images\jobs;

use Craft;
use craft\queue\BaseJob;
use Override;
use parallelogram\images\craft\BatchWarming;
use parallelogram\images\Plugin;

/**
 * Deletes every variant of one image and makes the assigned ones again.
 *
 * @api
 */
final class RebuildJob extends BaseJob
{
    public int $assetId = 0;

    #[Override]
    public function execute($queue): void
    {
        $warming = Plugin::getInstance()?->warming();

        if (null !== $warming) {
            (new BatchWarming($warming))->rebuild([$this->assetId]);
        }
    }

    #[Override]
    protected function defaultDescription(): ?string
    {
        return Craft::t('p-images', message: 'Rebuilding image variants');
    }
}
