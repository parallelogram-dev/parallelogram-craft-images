<?php

declare(strict_types=1);

namespace parallelogram\images\jobs;

use Craft;
use craft\elements\Asset;
use craft\errors\AssetException;
use craft\queue\BaseJob;
use Override;
use parallelogram\images\craft\PlaceholderMaker;
use parallelogram\images\delivery\GenerationFailedException;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Works out one uploaded or replaced image's placeholder colour.
 *
 * @api
 */
final class PlaceholderJob extends BaseJob
{
    public int $assetId = 0;

    /**
     * @throws AssetException|InvalidConfigException|GenerationFailedException|Exception
     */
    private static function makeFor(mixed $asset): void
    {
        if ($asset instanceof Asset) {
            PlaceholderMaker::forThisServer()->make($asset);
        }
    }

    /**
     * @throws AssetException|InvalidConfigException|GenerationFailedException When the image can't be read.
     * @throws Exception When the colour can't be stored.
     */
    #[Override]
    public function execute($queue): void
    {
        self::makeFor(Asset::find()->id($this->assetId)->status(null)->one());
    }

    #[Override]
    protected function defaultDescription(): ?string
    {
        return Craft::t('p-images', message: 'Working out an image’s placeholder colour');
    }
}
