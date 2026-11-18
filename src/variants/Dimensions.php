<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

/**
 * An image's width and height in pixels.
 *
 * @api
 */
final readonly class Dimensions
{
    /**
     * @throws InvalidVariantException When either side is less than 1.
     */
    public function __construct(
        public int $width,
        public int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidVariantException('An image needs a width and height of at least 1px to be resized.');
        }
    }
}
