<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Craft;
use craft\elements\Asset;
use craft\fields\Assets;
use parallelogram\images\sizing\FieldAssignments;

use function array_keys;

/**
 * Works out which size sets `p-images/warm` makes ahead of time, from the
 * field assignments on the Pre-generate tab.
 *
 * @internal
 */
final readonly class SizeSetScope
{
    public function __construct(
        private FieldAssignments $assignments,
    ) {}

    /**
     * The size sets for an image, whether or not anything refers to it yet:
     * those assigned to any Assets field that accepts its volume, since the
     * image can only be placed in such a field.
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
}
