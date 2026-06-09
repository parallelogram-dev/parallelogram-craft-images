<?php

namespace parallelogram\imagevariants\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use parallelogram\imagevariants\Plugin;

/**
 * Rebuild one asset's variants as a queue job — the asset edit screen's
 * "Rebuild image variants" action pushes this, so a regenerate (which deletes
 * then re-encodes, and can take a few seconds) never blocks the edit request.
 * Runs the same Warmer::rebuild() as the index action and the CLI.
 */
class RebuildJob extends BaseJob
{
    public int $assetId;

    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->kind('image')->one();

        if ($asset instanceof Asset) {
            Plugin::getInstance()->warmer->rebuild($asset);
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('image-variants', 'Rebuilding image variants');
    }
}
