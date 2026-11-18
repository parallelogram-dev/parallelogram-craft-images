<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use function sprintf;

/**
 * Site-wide processing that changes every variant's pixels, so it is part of
 * every variant's hash.
 *
 * @api
 */
final readonly class Processing
{
    public function __construct(
        public bool $autoOrient = true,
        public bool $convertToSrgb = true,
        public float $sharpenAmount = 0.5,
    ) {}

    public function toString(): string
    {
        return sprintf(
            'orient:%d,srgb:%d,sharpen:%.2F',
            (int) $this->autoOrient,
            (int) $this->convertToSrgb,
            $this->sharpenAmount,
        );
    }
}
