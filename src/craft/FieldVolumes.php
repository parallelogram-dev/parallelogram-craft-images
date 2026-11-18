<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Craft;
use craft\fields\Assets;
use yii\base\InvalidConfigException;

use function is_array;
use function str_starts_with;
use function substr;

/**
 * Which volumes an Assets field accepts images from.
 *
 * @internal
 */
final class FieldVolumes
{
    /**
     * Whether the field's sources include a volume: all sources, the volume
     * itself, or a folder in it.
     */
    public static function accept(Assets $field, string $volumeUid): bool
    {
        $sources = $field->sources;

        if (! is_array($sources)) {
            return true;
        }

        foreach ($sources as $source) {
            if (self::isInVolume($source, $volumeUid)) {
                return true;
            }
        }

        return false;
    }

    private static function isInVolume(string $source, string $volumeUid): bool
    {
        if (str_starts_with($source, 'volume:')) {
            return substr($source, offset: 7) === $volumeUid;
        }

        if (! str_starts_with($source, 'folder:')) {
            return false;
        }

        $folder = Craft::$app->getAssets()->getFolderByUid(substr($source, offset: 7));

        try {
            return $folder?->getVolume()->uid === $volumeUid;
        } catch (InvalidConfigException) {
            return false;
        }
    }
}
