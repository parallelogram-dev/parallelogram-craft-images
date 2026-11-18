<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\errors\InvalidFieldException;
use Illuminate\Support\Collection;

use function intval;

/**
 * The images in one of an element's Assets fields.
 *
 * @internal
 */
final class FieldAssets
{
    /**
     * @return list<int> Asset IDs, using eager-loaded values when there are some.
     */
    public static function idsIn(ElementInterface $element, string $handle): array
    {
        try {
            return self::idsFrom($element->getFieldValue($handle));
        } catch (InvalidFieldException) {
            return [];
        }
    }

    /**
     * @return list<int>
     */
    private static function idsFrom(mixed $value): array
    {
        $ids = [];

        if ($value instanceof AssetQuery) {
            foreach ($value->kind(Asset::KIND_IMAGE)->ids() as $id) {
                $ids[] = intval($id);
            }

            return $ids;
        }

        if (! $value instanceof Collection) {
            return $ids;
        }

        foreach ($value->whereInstanceOf(Asset::class)->all() as $asset) {
            if (Asset::KIND_IMAGE !== $asset->kind || null === $asset->id) {
                continue;
            }

            $ids[] = $asset->id;
        }

        return $ids;
    }
}
