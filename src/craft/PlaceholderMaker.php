<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use craft\errors\AssetException;
use parallelogram\images\colour\ColourSamplerInterface;
use parallelogram\images\colour\DominantColour;
use parallelogram\images\colour\GdSampler;
use parallelogram\images\colour\ImagickSampler;
use parallelogram\images\delivery\GenerationFailedException;
use yii\base\Exception;
use yii\base\InvalidConfigException;

use function extension_loaded;

/**
 * Works out an asset's placeholder colour and stores it.
 *
 * @internal
 */
final readonly class PlaceholderMaker
{
    public function __construct(
        private ColourSamplerInterface $sampler,
    ) {}

    /**
     * ImageMagick when it's installed, otherwise GD.
     */
    public static function forThisServer(): self
    {
        return new self(extension_loaded('imagick') ? new ImagickSampler() : new GdSampler());
    }

    /**
     * The colour stored, or null for assets that don't get one: those
     * without a placeholder field, and files that aren't resized.
     *
     * @throws AssetException|InvalidConfigException When the file can't be read.
     * @throws GenerationFailedException When the file isn't an image that can be read.
     * @throws Exception When the colour can't be stored.
     */
    public function make(Asset $asset): ?string
    {
        if (null === PlaceholderStore::field($asset) || null === AssetSource::from($asset)) {
            return null;
        }

        $colour = DominantColour::of($this->sampler->pixels($asset->getContents()));

        if (null === $colour) {
            return null;
        }

        PlaceholderStore::write($asset, $colour);

        return $colour;
    }
}
