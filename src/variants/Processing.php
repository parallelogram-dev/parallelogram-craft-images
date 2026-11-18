<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use function sprintf;

/**
 * Site-wide processing that changes every variant's pixels, so it is part of
 * every variant's hash. The AVIF effort only changes how hard the encoder
 * works for a smaller file, not the picture, so it is not: changing it leaves
 * existing variants valid.
 *
 * @api
 */
final readonly class Processing
{
    public function __construct(
        public bool $autoOrient = true,
        public bool $convertToSrgb = true,
        public float $sharpenAmount = 0.5,
        public int $avifEffort = self::DEFAULT_AVIF_EFFORT,
    ) {}

    /**
     * Matches libheif's and GD's own default encoder speed.
     */
    public const int DEFAULT_AVIF_EFFORT = 3;

    public const int MAX_AVIF_EFFORT = 9;

    /**
     * The encoder speed for the effort, the way libvips converts it: effort 0
     * is the slowest and smallest, 9 the quickest.
     */
    public function avifSpeed(): int
    {
        return self::MAX_AVIF_EFFORT - $this->avifEffort;
    }

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
