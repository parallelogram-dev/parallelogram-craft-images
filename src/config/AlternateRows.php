<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\AlternateImages;

use function array_filter;
use function is_array;

/**
 * The CP's alternate image table: one row per size set, with `sizeSet`,
 * `field`, `media` and `alternateSizeSet` columns.
 *
 * @internal
 */
final class AlternateRows
{
    /**
     * Rows for the CP table.
     *
     * @return list<array{sizeSet: string, field: string, media: string, alternateSizeSet: string}>
     */
    public static function rows(AlternateImages $alternates): array
    {
        $rows = [];

        foreach ($alternates->bySizeSet as $handle => $alternate) {
            $rows[] = [
                'sizeSet'          => $handle,
                'field'            => $alternate->fieldUid,
                'media'            => $alternate->media,
                'alternateSizeSet' => $alternate->sizeSet ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Reads the CP table's rows. Rows without a size set are skipped, and a
     * later row for the same size set replaces an earlier one.
     *
     * @param array<array-key, mixed> $rows
     *
     * @return array<string, array{field: string, media: string, sizeSet: string|null}>
     */
    public static function toConfigs(array $rows): array
    {
        $configs = [];

        foreach (array_filter($rows, is_array(...)) as $row) {
            $handle    = ConfigValues::string($row['sizeSet'] ?? null);
            $alternate = ConfigValues::string($row['alternateSizeSet'] ?? null);

            if ('' === $handle) {
                continue;
            }

            $configs[$handle] = [
                'field'   => ConfigValues::string($row['field'] ?? null),
                'media'   => ConfigValues::string($row['media'] ?? null),
                'sizeSet' => '' === $alternate ? null : $alternate,
            ];
        }

        return $configs;
    }
}
