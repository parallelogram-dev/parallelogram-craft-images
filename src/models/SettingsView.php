<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\config\SizeSetTable;
use parallelogram\images\sizing\InvalidSizeSetException;

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
