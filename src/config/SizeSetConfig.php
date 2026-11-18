<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\SizeSet;

use function array_map;
use function ctype_digit;
use function explode;
use function implode;
use function is_string;
use function strtr;

/**
 * Reads and writes size sets in the shape they are stored in project config:
 *
 * ```php
 * 'card' => [
 *     'widths'  => [260, 480, 640],   // or '260, 480, 640'
 *     'sizes'   => '(min-width: 908px) 33vw, 50vw',
 *     'ratio'   => '4:3',             // optional
 *     'mode'    => 'crop',            // optional
 *     'formats' => ['avif', 'jpg'],   // optional, defaults to the site's
 *     'quality' => ['avif' => 55],    // optional
 * ]
 * ```
 *
 * @api
 */
final class SizeSetConfig
{
    /**
     * @param array<array-key, mixed> $config
     * @param non-empty-list<Format> $defaultFormats Used when the config lists none.
     *
     * @throws InvalidSizeSetException When any value cannot be used.
     */
    public static function fromArray(string $handle, array $config, array $defaultFormats): SizeSet
    {
        return new SizeSet(
            $handle,
            self::widths($handle, $config['widths'] ?? null),
            ConfigValues::string($config['sizes'] ?? null),
            EncodingConfig::fromArray($handle, $config, $defaultFormats),
            FitConfig::fromArray($handle, $config),
        );
    }

    /**
     * Reads a width list typed by hand, such as `480, 720 960`.
     *
     * @return list<int>
     *
     * @throws InvalidSizeSetException When a token is not a whole number.
     */
    public static function parseWidths(string $handle, string $value): array
    {
        $tokens = explode(' ', strtr($value, [',' => ' ', "\t" => ' ', "\n" => ' ', "\r" => ' ']));
        $widths = [];

        foreach ($tokens as $token) {
            if ('' === $token) {
                continue;
            }

            if (! ctype_digit($token)) {
                throw new InvalidSizeSetException(
                    "“{$handle}” has “{$token}” as a width. Widths are whole numbers of pixels.",
                );
            }

            $widths[] = (int) $token;
        }

        return $widths;
    }

    /**
     * @return array{widths: list<int>, sizes: string, ratio: string|null, mode: string, formats: list<string>, quality: array<string, int>}
     */
    public static function toArray(SizeSet $sizeSet): array
    {
        return [
            'widths'  => $sizeSet->widths,
            'sizes'   => $sizeSet->sizes,
            'ratio'   => $sizeSet->fit->ratio?->toString(),
            'mode'    => $sizeSet->fit->mode->value,
            'formats' => array_map(static fn(Format $format): string => $format->value, $sizeSet->encoding->formats),
            'quality' => $sizeSet->encoding->quality->toArray(),
        ];
    }

    /**
     * The widths as the CP shows them.
     */
    public static function widthsToString(SizeSet $sizeSet): string
    {
        return implode(', ', $sizeSet->widths);
    }

    /**
     * @return list<int>
     *
     * @throws InvalidSizeSetException
     */
    private static function widths(string $handle, mixed $value): array
    {
        if (is_string($value)) {
            return self::parseWidths($handle, $value);
        }

        return (
            ConfigValues::integers($value)
                ?? throw new InvalidSizeSetException("“{$handle}” needs a list of widths in whole pixels.")
        );
    }
}
