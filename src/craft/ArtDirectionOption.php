<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\picture\InvalidPictureException;
use parallelogram\images\picture\OptionReader;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_shift;
use function implode;
use function is_array;

/**
 * One entry of the `sources` option: a media query, and the asset and size
 * set to show when it matches. Either may be left out to use the picture's
 * own.
 *
 * ```twig
 * {{ asset.picture('wide', {sources: [
 *     {media: '(max-width: 600px)', asset: entry.mobileImage.one(), sizeSet: 'square'},
 * ]}) }}
 * ```
 *
 * @internal
 */
final readonly class ArtDirectionOption
{
    private const array KNOWN = ['asset', 'media', 'sizeSet'];

    public function __construct(
        public string $media,
        public ?Asset $asset = null,
        public ?string $sizeSet = null,
    ) {}

    /**
     * The `sources` option, or none when it's left out.
     *
     * @param array<array-key, mixed> $options
     *
     * @return list<self>
     *
     * @throws InvalidPictureException When it isn't a list of entries this can read.
     */
    public static function listFrom(array $options): array
    {
        if (! array_key_exists('sources', $options)) {
            return [];
        }

        if (! is_array($options['sources'])) {
            throw new InvalidPictureException('The sources option must be a list of {media, asset, sizeSet} hashes.');
        }

        $sources = $options['sources'];
        $entries = [];

        while ([] !== $sources) {
            $entries[] = self::from(array_shift($sources));
        }

        return $entries;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidPictureException
     */
    private static function asset(array $entry): ?Asset
    {
        if (null === ($entry['asset'] ?? null)) {
            return null;
        }

        if (! $entry['asset'] instanceof Asset) {
            throw new InvalidPictureException(
                'An art-directed source’s asset must be an asset, such as entry.mobileImage.one().',
            );
        }

        return $entry['asset'];
    }

    /**
     * @throws InvalidPictureException
     */
    private static function from(mixed $entry): self
    {
        if (! is_array($entry)) {
            throw new InvalidPictureException('Each art-directed source must be a {media, asset, sizeSet} hash.');
        }

        $unknown = array_diff(array_keys($entry), self::KNOWN);

        if ([] !== $unknown) {
            throw new InvalidPictureException(
                'Unknown art-directed source option: ' . implode(', ', $unknown) . '. Use media, asset or sizeSet.',
            );
        }

        $media = OptionReader::text($entry, key: 'media') ?? '';

        if ('' === $media) {
            throw new InvalidPictureException(
                'Each art-directed source needs a media query, such as (max-width: 600px).',
            );
        }

        return new self($media, self::asset($entry), OptionReader::text($entry, key: 'sizeSet'));
    }
}
