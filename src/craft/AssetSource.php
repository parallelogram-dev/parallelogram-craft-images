<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;
use yii\base\InvalidConfigException;

use function is_array;
use function is_numeric;

/**
 * Reads the facts variants depend on from a Craft asset.
 *
 * @internal
 */
final class AssetSource
{
    /**
     * Null for anything that isn't a resizable image: other kinds of file,
     * images without dimensions, or file names that leave nothing to use.
     */
    public static function from(Asset $asset): ?SourceImage
    {
        if (Asset::KIND_IMAGE !== $asset->kind || null === $asset->uid) {
            return null;
        }

        try {
            return new SourceImage(
                $asset->uid,
                $asset->getFilename(),
                new Dimensions($asset->getWidth() ?? 0, $asset->getHeight() ?? 0),
                $asset->dateModified?->getTimestamp() ?? 0,
                self::focalPoint($asset->getFocalPoint()),
            );
        } catch (InvalidVariantException|InvalidConfigException) {
            return null;
        }
    }

    private static function focalPoint(mixed $value): ?FocalPoint
    {
        if (! is_array($value) || ! is_numeric($value['x'] ?? null) || ! is_numeric($value['y'] ?? null)) {
            return null;
        }

        return new FocalPoint((float) $value['x'], (float) $value['y']);
    }
}
