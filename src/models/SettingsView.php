<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\config\AlternateRows;
use parallelogram\images\config\SizeSetTable;
use parallelogram\images\sizing\InvalidSizeSetException;

use function array_filter;
use function array_values;
use function is_array;

/**
 * What the settings screen needs from the settings, tolerating settings that
 * don't validate yet so the screen can still show them for fixing.
 *
 * @internal
 */
final readonly class SettingsView
{
    public function __construct(
        private Settings $settings,
    ) {}

    /**
     * Rows for the alternate image table: what was posted when redisplaying
     * after an error, otherwise the stored alternates.
     *
     * @return array<array-key, mixed>
     */
    public function alternateRows(): array
    {
        $posted = array_filter($this->settings->alternateRows, is_array(...));

        if ([] !== $posted) {
            return array_values($posted);
        }

        try {
            return AlternateRows::rows($this->settings->alternateImages());
        } catch (InvalidSizeSetException) {
            return [];
        }
    }

    /**
     * Size set handles a field is assigned, for its checkboxes.
     *
     * @return list<string>
     */
    public function handlesAssignedTo(string $fieldUid): array
    {
        try {
            return $this->settings->assignments()->sizeSetsFor($fieldUid, layoutUid: null);
        } catch (InvalidSizeSetException) {
            return [];
        }
    }

    /**
     * Handles of every size set that can be read.
     *
     * @return list<string>
     */
    public function sizeSetHandles(): array
    {
        try {
            return $this->settings->catalogue()->handles();
        } catch (InvalidSizeSetException) {
            return [];
        }
    }

    /**
     * Rows for the size set table: what was posted when redisplaying after an
     * error, otherwise the stored size sets.
     *
     * @return array<array-key, mixed>
     */
    public function sizeSetRows(): array
    {
        if ([] !== $this->settings->sizeSetRows) {
            return $this->settings->sizeSetRows;
        }

        try {
            return SizeSetTable::rows($this->settings->catalogue()->all());
        } catch (InvalidSizeSetException) {
            return [];
        }
    }
}
