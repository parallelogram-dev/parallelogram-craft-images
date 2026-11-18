<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;
use yii\base\InvalidConfigException;

use function in_array;
use function is_array;
use function is_numeric;
use function strtolower;

/**
 * Reads the facts variants depend on from a Craft asset.
 *
 * @internal
 */
final class AssetSource
{
    /**
     * Formats shown as uploaded: SVGs scale by themselves, and resizing a GIF
     * would lose its animation.
     */
    private const array UNTOUCHED = ['gif', 'svg'];

    /**
     * Null for anything that isn't a resizable image: other kinds of file,
     * SVGs and GIFs, images without dimensions, or file names that leave
     * nothing to use.
     */
    public static function from(Asset $asset): ?SourceImage
    {
        $uid = $asset->uid;

        if (null === $uid || ! self::isResizable($asset)) {
            return null;
        }

        try {
            return new SourceImage(
                $uid,
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

    private static function isResizable(Asset $asset): bool
    {
        return (
            Asset::KIND_IMAGE === $asset->kind
                && ! in_array(strtolower($asset->getExtension()), self::UNTOUCHED, strict: true)
        );
    }
}
