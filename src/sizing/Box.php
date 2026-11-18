<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

/**
 * The dimensions of one variant. A null height keeps the source's proportions.
 *
 * @api
 */
final readonly class Box
{
    public function __construct(
        public int $width,
        public ?int $height,
        public Mode $mode,
    ) {}
}
