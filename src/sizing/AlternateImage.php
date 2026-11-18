<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

/**
 * A size set's art-directed alternative: the image in an Assets field on the
 * asset itself (such as a square crop for phones), shown when a media query
 * matches, optionally in a different size set.
 *
 * @api
 */
final readonly class AlternateImage
{
    public function __construct(
        public string $fieldUid,
        public string $media,
        public ?string $sizeSet = null,
    ) {}
}
