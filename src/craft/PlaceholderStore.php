<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\errors\InvalidFieldException;
use craft\helpers\Db;
use craft\helpers\Json;
use parallelogram\images\colour\HexColour;
use parallelogram\images\fields\PlaceholderColourField;
use yii\base\Exception;

use function array_keys;
use function is_array;
use function is_string;

/**
 * Reads and writes an asset's placeholder colour.
 *
 * Writing goes straight to the asset's stored content for every site rather
 * than through an element save, so working a colour out never creates a
 * revision, fires save events or re-queues itself; only caches tagged with
 * that asset are cleared.
 *
 * @internal
 */
final class PlaceholderStore
{
    public static function colourOf(Asset $asset): ?string
    {
        $handle = self::field($asset)?->handle;

        if (null === $handle) {
            return null;
        }

        try {
            return HexColour::normalise($asset->getFieldValue($handle));
        } catch (InvalidFieldException) {
            return null;
        }
    }

    /**
     * The placeholder field in the asset's volume layout, if it has one.
     */
    public static function field(Asset $asset): ?PlaceholderColourField
    {
        foreach ($asset->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if ($field instanceof PlaceholderColourField) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Stores a colour, returning whether there was a placeholder field to
     * store it in. Assets already loaded keep their old value; load the
     * asset again to read it.
     *
     * @throws Exception When the database can't be written.
     */
    public static function write(Asset $asset, string $colour): bool
    {
        $element = self::field($asset)?->layoutElement;

        if (null === $element || null === $element->uid || null === $asset->id) {
            return false;
        }

        $stored = (new Query())->select(['id', 'content'])
            ->from(Table::ELEMENTS_SITES)
            ->where(['elementId' => $asset->id])
            ->pairs();

        foreach (array_keys($stored) as $id) {
            $content                = self::content($stored[$id] ?? null);
            $content[$element->uid] = $colour;
            Db::update(Table::ELEMENTS_SITES, ['content' => $content], ['id' => $id]);
        }

        Craft::$app->getElements()->invalidateCachesForElement($asset);

        return true;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function content(mixed $stored): array
    {
        if (is_string($stored)) {
            return self::array(Json::decodeIfJson($stored));
        }

        return self::array($stored);
    }
}
