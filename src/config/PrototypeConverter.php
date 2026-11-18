<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use Closure;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;

/**
 * Converts the settings saved by the prototype ("Parallelogram Image
 * Variants") into size sets and field assignments.
 *
 * @api
 */
final class PrototypeConverter
{
    /**
     * @param mixed $matrices The prototype's `matrices` setting.
     * @param mixed $assignments The prototype's `fieldAssignments` setting, keyed by field handle.
     * @param Closure(string): ?string $fieldUidForHandle Looks up a field's UID by its handle.
     */
    public static function convert(mixed $matrices, mixed $assignments, Closure $fieldUidForHandle): PrototypeConversion
    {
        $matrix   = new PrototypeMatrix();
        $sizeSets = [];

        foreach (is_array($matrices) ? array_filter($matrices, is_array(...)) : [] as $handle => $definition) {
            $config = $matrix->convert((string) $handle, $definition);

            if (null === $config) {
                continue;
            }

            $sizeSets[(string) $handle] = $config;
        }

        $notes = $matrix->notes;

        return new PrototypeConversion(
            $sizeSets,
            self::assignments($assignments, array_keys($sizeSets), $fieldUidForHandle, $notes),
            $notes,
        );
    }

    /**
     * @param list<string> $handles Size sets that were converted.
     * @param Closure(string): ?string $fieldUidForHandle
     * @param list<string> $notes
     *
     * @return list<array{fieldUid: string, layoutUid: null, sizeSets: list<string>}>
     */
    private static function assignments(
        mixed $assignments,
        array $handles,
        Closure $fieldUidForHandle,
        array &$notes,
    ): array {
        $converted = [];

        $byField = is_array($assignments)
            ? array_combine(array_keys($assignments), array_map(ConfigValues::strings(...), array_values($assignments)))
            : [];

        foreach ($byField as $fieldHandle => $assigned) {
            $uid      = is_string($fieldHandle) ? $fieldUidForHandle($fieldHandle) : null;
            $sizeSets = [];

            foreach ($assigned ?? [] as $handle) {
                if (! in_array($handle, $handles, strict: true)) {
                    continue;
                }

                $sizeSets[] = $handle;
            }

            if (null === $uid) {
                $notes[] = "The field “{$fieldHandle}” no longer exists, so its assignment was dropped.";
                continue;
            }

            if ([] === $sizeSets) {
                continue;
            }

            $converted[] = ['fieldUid' => $uid, 'layoutUid' => null, 'sizeSets' => $sizeSets];
        }

        return $converted;
    }
}
