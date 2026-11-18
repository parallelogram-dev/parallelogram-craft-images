<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\AlternateImage;
use parallelogram\images\sizing\AlternateImages;
use parallelogram\images\sizing\InvalidSizeSetException;

use function array_filter;
use function count;
use function is_array;

/**
 * Reads and writes alternate images, stored keyed by size set handle:
 *
 * ```php
 * 'wide' => [
 *     'field'   => '60f35b38-…',          // UID of an Assets field on the asset's layout
 *     'media'   => '(max-width: 430px)',
 *     'sizeSet' => 'square',              // optional, defaults to the picture's own
 * ]
 * ```
 *
 * The CP posts them as table rows instead, which {@see AlternateRows} reads.
 *
 * @internal
 */
final class AlternateConfig
{
    /**
     * @throws InvalidSizeSetException When an entry can't be used.
     */
    public static function fromArray(mixed $config): AlternateImages
    {
        if (! is_array($config)) {
            throw new InvalidSizeSetException('Alternate images must be a list keyed by size set.');
        }

        $alternates = [];

        foreach (array_filter($config, is_array(...)) as $handle => $entry) {
            $alternates[(string) $handle] = self::alternate((string) $handle, $entry);
        }

        if (count($alternates) !== count($config)) {
            throw new InvalidSizeSetException('Each alternate image needs a field and a media query.');
        }

        return new AlternateImages($alternates);
    }

    /**
     * @return array<string, array{field: string, media: string, sizeSet: string|null}>
     */
    public static function toArray(AlternateImages $alternates): array
    {
        $config = [];

        foreach ($alternates->bySizeSet as $handle => $alternate) {
            $config[$handle] = [
                'field'   => $alternate->fieldUid,
                'media'   => $alternate->media,
                'sizeSet' => $alternate->sizeSet,
            ];
        }

        return $config;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidSizeSetException
     */
    private static function alternate(string $handle, array $entry): AlternateImage
    {
        $field   = ConfigValues::string($entry['field'] ?? null);
        $media   = ConfigValues::string($entry['media'] ?? null);
        $sizeSet = ConfigValues::string($entry['sizeSet'] ?? null);

        if ('' === $field) {
            throw new InvalidSizeSetException("Choose the field that holds “{$handle}”’s alternate image.");
        }

        if ('' === $media) {
            throw new InvalidSizeSetException(
                "Give “{$handle}”’s alternate image a media query, such as (max-width: 430px).",
            );
        }

        return new AlternateImage($field, $media, '' === $sizeSet ? null : $sizeSet);
    }
}
