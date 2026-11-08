<?php

declare(strict_types=1);

namespace parallelogram\imagevariants\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use parallelogram\imagevariants\Plugin;

/**
 * Generate an asset's missing variants after its file has moved.
 *
 * A slug change moves every image on the entry inside one save, so generating
 * there can pass PHP's time limit and kill the request after files have moved
 * but before the database commits. Generation skips variants that already
 * exist, so running this more than once is harmless.
 */
class GenerateJob extends BaseJob
{
    public int $assetId;

    /** @var string[] */
    public array $matrixHandles = [];

    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->kind('image')->one();

        if ($asset instanceof Asset) {
            Plugin::getInstance()->generator->generate($asset, $this->matrixHandles);
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('image-variants', 'Generating image variants');
    }
}
