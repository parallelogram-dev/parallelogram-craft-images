<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;

use function array_map;

/**
 * Reads the `formats` and `quality` of a size set's config.
 *
 * @internal
 */
final class EncodingConfig
{
    /**
     * @param array<array-key, mixed> $config
     * @param non-empty-list<Format> $defaultFormats Used when the config lists none.
     *
     * @throws InvalidSizeSetException When a format or quality value cannot be used.
     */
    public static function fromArray(string $handle, array $config, array $defaultFormats): Encoding
    {
        $quality = ConfigValues::integerMap($config['quality'] ?? [])
            ?? throw new InvalidSizeSetException(
                "“{$handle}” has quality values that aren't formats with whole numbers.",
            );

        return new Encoding(
            new QualityTable($quality),
            ...self::formats($handle, $config['formats'] ?? [], $defaultFormats),
        );
    }

    /**
     * @param non-empty-list<Format> $defaults
     *
     * @return non-empty-list<Format>
     *
     * @throws InvalidSizeSetException
     */
    private static function formats(string $handle, mixed $value, array $defaults): array
    {
        $names = ConfigValues::strings($value)
            ?? throw new InvalidSizeSetException("“{$handle}” has formats that aren't a list of names.");

        if ([] === $names) {
            return $defaults;
        }

        return array_map(
            static fn(string $name): Format => (
                Format::tryFrom($name)
                ?? throw new InvalidSizeSetException("“{$handle}” lists “{$name}”, which isn't AVIF, WebP, JPG or PNG.")
            ),
            $names,
        );
    }
}
