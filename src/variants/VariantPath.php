<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use parallelogram\images\sizing\Format;

use function explode;
use function implode;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Builds and reads variant paths, relative to the variant filesystem's root:
 * `{prefix}/{assetUid}/{hash}/{basename}.{ext}`.
 *
 * Only the prefix is configurable. The rest is fixed so each asset's variants
 * share one folder (cleanup removes the folder), moving or renaming an asset
 * never changes them, and a request can always be traced back to its variant.
 *
 * @api
 */
final readonly class VariantPath
{
    /**
     * `{uid}/{hash}/{basename}.{ext}`, where the basename may hold dots but no
     * slashes.
     */
    private const string PATH_PATTERN = '#^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/([0-9a-f]{16})/([^/]+)\.([a-z]+)$#';

    public string $prefix;

    /**
     * @throws InvalidVariantException When the prefix is empty or has unsafe segments.
     */
    public function __construct(string $prefix)
    {
        $prefix = trim($prefix, characters: '/ ');

        if ('' === $prefix) {
            throw new InvalidVariantException('Choose a path prefix for image variants, such as asset/variant.');
        }

        foreach (explode('/', $prefix) as $segment) {
            if (1 !== preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $segment)) {
                throw new InvalidVariantException(
                    "“{$prefix}” can't be used as a variant prefix. Use letters, numbers, dashes, underscores and slashes.",
                );
            }
        }

        $this->prefix = $prefix;
    }

    /**
     * The folder holding every variant of one asset.
     */
    public function folderFor(string $assetUid): string
    {
        return "{$this->prefix}/{$assetUid}";
    }

    /**
     * Reads a requested path, or returns null when it isn't a well-formed
     * variant path under this prefix.
     */
    public function parse(string $path): ?ParsedVariantPath
    {
        $trimmed = trim($path, characters: '/');
        $matches = [];

        if (
            ! str_starts_with($trimmed, "{$this->prefix}/")
            || 1 !== preg_match(self::PATH_PATTERN, substr($trimmed, offset: strlen($this->prefix) + 1), $matches)
        ) {
            return null;
        }

        [, $uid, $hash, $basename, $extension] = $matches + ['', '', '', '', ''];
        $format = Format::tryFrom($extension);

        return null === $format ? null : new ParsedVariantPath($uid, $hash, $basename, $format);
    }

    /**
     * Where one variant of a source image lives.
     */
    public function pathFor(SourceImage $source, Format $format, string $hash): string
    {
        return implode('/', [$this->folderFor($source->uid), $hash, "{$source->basename()}.{$format->value}"]);
    }
}
