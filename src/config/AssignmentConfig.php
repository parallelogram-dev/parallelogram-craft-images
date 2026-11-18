<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\InvalidSizeSetException;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_string;

/**
 * Reads and writes field assignments. Stored as a list:
 *
 * ```php
 * [['fieldUid' => '…', 'layoutUid' => null, 'sizeSets' => ['card', 'tile']]]
 * ```
 *
 * The CP posts them as checkboxes keyed by field UID instead, which
 * {@see fromInput()} reads.
 *
 * @internal
 */
final class AssignmentConfig
{
    /**
     * @throws InvalidSizeSetException When an entry is the wrong shape.
     */
    public static function fromArray(mixed $config): FieldAssignments
    {
        if (! is_array($config)) {
            throw new InvalidSizeSetException('Field assignments must be a list.');
        }

        $entries = array_filter($config, is_array(...));

        if (count($entries) !== count($config)) {
            throw new InvalidSizeSetException('Each field assignment needs a field and its size sets.');
        }

        $assignments = [];

        foreach ($entries as $entry) {
            $layoutUid     = ConfigValues::string($entry['layoutUid'] ?? null);
            $assignments[] = new FieldAssignment(
                ConfigValues::string($entry['fieldUid'] ?? null),
                ConfigValues::strings($entry['sizeSets'] ?? null)
                ?? throw new InvalidSizeSetException('A field assignment lists size sets that aren\'t names.'),
                '' === $layoutUid ? null : $layoutUid,
            );
        }

        return new FieldAssignments($assignments);
    }

    /**
     * Reads the CP's checkboxes: size set handles keyed by field UID. Fields
     * with nothing ticked are left out.
     */
    public static function fromInput(mixed $input): FieldAssignments
    {
        if (! is_array($input)) {
            return new FieldAssignments();
        }

        $assignments = [];

        foreach (array_combine(
            array_keys($input),
            array_map(ConfigValues::strings(...), array_values($input)),
        ) as $fieldUid => $handles) {
            $ticked = is_string($fieldUid) && null !== $handles
                ? array_filter($handles, static fn(string $handle): bool => '' !== $handle)
                : [];

            if ([] === $ticked) {
                continue;
            }

            $assignments[] = new FieldAssignment((string) $fieldUid, [...$ticked]);
        }

        return new FieldAssignments($assignments);
    }

    /**
     * @return list<array{fieldUid: string, layoutUid: string|null, sizeSets: list<string>}>
     */
    public static function toArray(FieldAssignments $assignments): array
    {
        $entries = [];

        foreach ($assignments->assignments as $assignment) {
            $entries[] = [
                'fieldUid'  => $assignment->fieldUid,
                'layoutUid' => $assignment->layoutUid,
                'sizeSets'  => $assignment->sizeSets,
            ];
        }

        return $entries;
    }
}
