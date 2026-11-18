<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

/**
 * An alternative image for screens matching a media query, such as a square
 * crop for phones, or no image at all (a transparent pixel) when there's no
 * picture, so small screens don't download it.
 *
 * @api
 */
final readonly class ArtDirection
{
    public function __construct(
        public string $media,
        public ?PictureSources $picture = null,
    ) {}
}
