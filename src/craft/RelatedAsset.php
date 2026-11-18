<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\errors\InvalidFieldException;
use Illuminate\Support\Collection;

/**
 * The first asset in one of an asset's own Assets fields, found by the
 * field's UID so a handle overridden in the field layout still works, and
 * using eager-loaded values when there are some.
 *
 * @internal
 */
final class RelatedAsset
{
    public static function in(Asset $asset, string $fieldUid): ?Asset
    {
        foreach ($asset->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if ($field->uid === $fieldUid && null !== $field->handle) {
                return self::value($asset, $field->handle);
            }
        }

        return null;
    }

    private static function asset(mixed $element): ?Asset
    {
        return $element instanceof Asset ? $element : null;
    }

    private static function first(mixed $value): ?Asset
    {
        if ($value instanceof ElementQueryInterface) {
            return self::asset($value->one());
        }

        return $value instanceof Collection ? self::asset($value->first()) : null;
    }

    private static function value(Asset $asset, string $handle): ?Asset
    {
        try {
            return self::first($asset->getFieldValue($handle));
        } catch (InvalidFieldException) {
            return null;
        }
    }
}
