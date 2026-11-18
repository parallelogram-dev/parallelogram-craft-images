<?php

declare(strict_types=1);

namespace parallelogram\images\jobs;

use Craft;
use craft\queue\BaseJob;
use Override;
use parallelogram\images\craft\BatchWarming;
use parallelogram\images\Plugin;

/**
 * Makes the missing variants of every image, or of one volume's, showing its
 * progress in Craft's queue. Files already there are left alone, so running
 * it again carries on where a stopped run left off.
 *
 * @api
 */
final class WarmJob extends BaseJob
{
    public ?string $sizeSet = null;

    public ?string $volume = null;

    #[Override]
    public function execute($queue): void
    {
        $warming = Plugin::getInstance()?->warming();

        if (null === $warming) {
            return;
        }

        $batch = new BatchWarming($warming);

        $batch->warm($batch->imageIds($this->volume) ?? [], $this->sizeSet, function (int $done, int $total) use (
            $queue,
        ): void {
            $this->setProgress($queue, $done / $total);
        });
    }

    #[Override]
    protected function defaultDescription(): ?string
    {
        return Craft::t('p-images', message: 'Making image variants');
    }
}
