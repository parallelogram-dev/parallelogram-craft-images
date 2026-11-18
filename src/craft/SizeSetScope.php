<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\fields\Assets;
use parallelogram\images\sizing\FieldAssignments;

use function array_keys;

/**
 * Works out which size sets to make ahead of time, from the field
 * assignments on the Pre-generate tab.
 *
 * @internal
 */
final readonly class SizeSetScope
{
    public function __construct(
        private FieldAssignments $assignments,
    ) {}

    /**
     * The size sets for a new or replaced image, before anything refers to
     * it: those assigned to any Assets field that accepts its volume, since
     * the image can only be placed in such a field.
     *
     * @return list<string>
     */
    public function forVolume(Asset $asset): array
    {
        $volumeUid = AssetFiles::volumeUid($asset);
        $handles   = [];

        foreach ($this->assignments->assignments as $assignment) {
            $field = Craft::$app->getFields()->getFieldByUid($assignment->fieldUid);

            if (null === $volumeUid || ! $field instanceof Assets || ! FieldVolumes::accept($field, $volumeUid)) {
                continue;
            }

            foreach ($assignment->sizeSets as $handle) {
                $handles[$handle] = true;
            }
        }

        return array_keys($handles);
    }

    /**
     * The images in an element's assigned fields that need warming after it
     * is saved, one request per image with every size set it needs.
     *
     * @param list<string>|null $changed Handles of the fields that changed, or null for every field (a new element).
     *
     * @return list<WarmRequest>
     */
    public function inElement(ElementInterface $element, ?array $changed): array
    {
        return (new ElementSizeSets($this->assignments))->requests($element, $changed);
    }
}
