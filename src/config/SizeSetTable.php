<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\SizeSet;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function in_array;
use function is_array;

/**
 * Builds the rows of the CP's size set table from stored size sets, and
 * checks posted rows for repeated handles. {@see SizeSetRows} reads them back.
 *
 * @internal
 */
final class SizeSetTable
{
    /**
     * Handles used by more than one row.
     *
     * @param array<array-key, mixed> $rows
     *
     * @return list<string>
     */
    public static function duplicateHandles(array $rows): array
    {
        $seen       = [];
        $duplicates = [];

        foreach (array_filter($rows, is_array(...)) as $row) {
            $handle = ConfigValues::string($row['handle'] ?? null);

            if ('' !== $handle && array_key_exists($handle, $seen)) {
                $duplicates[$handle] = true;
            }

            $seen[$handle] = true;
        }

        return array_keys($duplicates);
    }

    /**
     * @param list<SizeSet> $sizeSets
     *
     * @return list<array<string, string|bool>>
     */
    public static function rows(array $sizeSets): array
    {
        return array_map(static function (SizeSet $sizeSet): array {
            $row = [
                'handle' => $sizeSet->handle,
                'ratio'  => $sizeSet->fit->ratio?->toString() ?? '',
                'widths' => SizeSetConfig::widthsToString($sizeSet),
                'sizes'  => $sizeSet->sizes,
                'mode'   => $sizeSet->fit->mode->value,
            ];

            foreach (Format::cases() as $format) {
                $row[$format->value] = in_array($format, $sizeSet->encoding->formats, strict: true);
            }

            return $row;
        }, $sizeSets);
    }
}
