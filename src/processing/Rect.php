<?php

declare(strict_types=1);

namespace parallelogram\images\processing;

/**
 * A rectangle within an image, in pixels from its top-left corner.
 *
 * @api
 */
final readonly class Rect
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}
}
