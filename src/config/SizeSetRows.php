<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\Format;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;

/**
 * Reads the rows posted by the CP's size set table. A row has `handle`, `ratio`, `widths`, `sizes`, `mode` and one checkbox per
 * format. Per-format quality isn't in the table, so a row keeps whatever
 * quality its size set already had. {@see SizeSetTable} builds the rows.
 *
 * @internal
 */
final class SizeSetRows
{
    /**
     * Size set configs keyed by handle. Rows without a handle are skipped, and
     * a later row with a repeated handle replaces an earlier one (check
     * {@see SizeSetTable::duplicateHandles()} first).
     *
     * @param array<array-key, mixed> $rows
     * @param array<array-key, mixed> $existing Current configs, for quality values the table doesn't show.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function toConfigs(array $rows, array $existing): array
    {
        $configs = [];

        foreach (array_filter($rows, is_array(...)) as $row) {
            $handle = ConfigValues::string($row['handle'] ?? null);

            if ('' === $handle) {
                continue;
            }

            $configs[$handle] = [
                'widths'  => ConfigValues::string($row['widths'] ?? null),
                'sizes'   => ConfigValues::string($row['sizes'] ?? null),
                'ratio'   => ConfigValues::string($row['ratio'] ?? null),
                'mode'    => ConfigValues::string($row['mode'] ?? null),
                'formats' => self::tickedFormats($row),
                'quality' => self::existingQuality($existing[$handle] ?? null),
            ];
        }

        return $configs;
    }

    /**
     * @return array<string, int>
     */
    private static function existingQuality(mixed $config): array
    {
        return is_array($config) ? ConfigValues::integerMap($config['quality'] ?? []) ?? [] : [];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return list<string>
     */
    private static function tickedFormats(array $row): array
    {
        return array_values(array_map(
            static fn(Format $format): string => $format->value,
            array_filter(Format::cases(), static fn(Format $format): bool => in_array(
                $row[$format->value] ?? null,
                [true, 1, '1', 'on'],
                strict: true,
            )),
        ));
    }
}
