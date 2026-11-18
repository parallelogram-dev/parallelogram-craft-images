<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use function pathinfo;
use function preg_match;
use function str_replace;
use function trim;

use const PATHINFO_FILENAME;

/**
 * The facts about an original image that variants depend on: which asset it
 * is, its file name, its size, when the file last changed and its focal point.
 *
 * @api
 */
final readonly class SourceImage
{
    public FocalPoint $focalPoint;

    /**
     * @throws InvalidVariantException When the UID or file name cannot be used.
     */
    public function __construct(
        public string $uid,
        public string $filename,
        public Dimensions $dimensions,
        public int $modifiedAt,
        ?FocalPoint $focalPoint = null,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uid)) {
            throw new InvalidVariantException("“{$uid}” is not an asset UID.");
        }

        if ('' === $this->basename()) {
            throw new InvalidVariantException("Asset {$uid} has no usable file name.");
        }

        $this->focalPoint = $focalPoint ?? new FocalPoint();
    }

    /**
     * The file name without its extension, safe to use as one path segment.
     */
    public function basename(): string
    {
        return trim(
            str_replace(
                search: ['/', '\\'],
                replace: '-',
                subject: pathinfo($this->filename, PATHINFO_FILENAME),
            ),
            characters: '. ',
        );
    }
}
