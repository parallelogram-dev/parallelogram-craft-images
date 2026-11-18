<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use function array_diff;
use function array_keys;
use function implode;

/**
 * The options a template gives `asset.picture()`:
 *
 * - `alt`: the alternative text, instead of the asset's own.
 * - `class`: classes for the `<img>`.
 * - `pictureClass`: classes for the `<picture>` around it.
 * - `sizes`: a `sizes` value, instead of the size set's.
 * - `lazysrc`: true to hand the sources to ParallelogramJS's Lazysrc, or
 *   false to opt out when the site makes Lazysrc the default.
 * - `priority`: true for the largest image above the fold, which then loads
 *   straight away with `fetchpriority="high"`, Lazysrc or not.
 * - `attributes`: any other `<img>` attributes. True writes the name alone;
 *   false or null leaves it out.
 * - `pictureAttributes`: the same, for the `<picture>`.
 * - `sources`: art-directed alternatives, read by the plugin itself. An entry
 *   with `blank: true` shows nothing (a transparent pixel) while its media
 *   query matches.
 *
 * @api
 */
final readonly class PictureOptions
{
    private const array KNOWN = [
        'alt',
        'attributes',
        'class',
        'lazysrc',
        'pictureAttributes',
        'pictureClass',
        'priority',
        'sizes',
        'sources',
    ];

    public function __construct(
        public string $alt = '',
        public ?string $sizes = null,
        public Loading $loading = Loading::Lazy,
        public ElementAttributes $image = new ElementAttributes(),
        public ElementAttributes $picture = new ElementAttributes(),
    ) {}

    /**
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidPictureException When an option is unknown or has the wrong type.
     */
    public static function fromArray(array $options, string $defaultAlt, Loading $default = Loading::Lazy): self
    {
        $unknown = array_diff(array_keys($options), self::KNOWN);

        if ([] !== $unknown) {
            throw new InvalidPictureException(
                'Unknown picture option: ' . implode(', ', $unknown) . '. Use ' . implode(', ', self::KNOWN) . '.',
            );
        }

        return new self(
            OptionReader::text($options, key: 'alt') ?? $defaultAlt,
            OptionReader::text($options, key: 'sizes'),
            self::loading($options, $default),
            new ElementAttributes(
                OptionReader::text($options, key: 'class'),
                OptionReader::attributes($options, key: 'attributes'),
            ),
            new ElementAttributes(
                OptionReader::text($options, key: 'pictureClass'),
                OptionReader::attributes($options, key: 'pictureAttributes'),
            ),
        );
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidPictureException
     */
    private static function loading(array $options, Loading $default): Loading
    {
        $priority = OptionReader::flag($options, key: 'priority') ?? false;
        $lazysrc  = OptionReader::flag($options, key: 'lazysrc') ?? Loading::Lazysrc === $default;

        if ($priority) {
            return Loading::Priority;
        }

        return $lazysrc ? Loading::Lazysrc : Loading::Lazy;
    }
}
