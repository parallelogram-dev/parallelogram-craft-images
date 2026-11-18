<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\picture\OriginalImage;
use yii\base\InvalidConfigException;

/**
 * Facts about an asset's file that may not be available, read without
 * throwing.
 *
 * @internal
 */
final class AssetFiles
{
    /**
     * The uploaded file, for showing as it is.
     */
    public static function original(Asset $asset): OriginalImage
    {
        return new OriginalImage(self::url($asset) ?? '', $asset->getWidth(), $asset->getHeight());
    }

    /**
     * The file's public URL, or null when its volume has none.
     */
    public static function url(Asset $asset): ?string
    {
        try {
            return $asset->getUrl();
        } catch (InvalidConfigException) {
            return null;
        }
    }

    /**
     * The UID of the asset's volume, or null when it can't be found.
     */
    public static function volumeUid(Asset $asset): ?string
    {
        try {
            return $asset->getVolume()->uid;
        } catch (InvalidConfigException) {
            return null;
        }
    }
}
