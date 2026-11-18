<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

/**
 * One file to generate for an image: its box, format and encoder quality.
 *
 * @api
 */
final readonly class Variant
{
    public function __construct(
        public string $sizeSet,
        public Box $box,
        public Format $format,
        public int $quality,
    ) {}
}
