<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use function max;
use function min;
use function sprintf;

/**
 * Where a crop should centre on, as fractions of the width and height.
 * Values outside 0–1 are clamped.
 *
 * @api
 */
final readonly class FocalPoint
{
    public float $x;

    public float $y;

    public function __construct(float $x = 0.5, float $y = 0.5)
    {
        $this->x = max(0.0, min(1.0, $x));
        $this->y = max(0.0, min(1.0, $y));
    }

    /**
     * Four decimal places, which is finer than a pixel on any realistic image.
     */
    public function toString(): string
    {
        return sprintf('%.4F,%.4F', $this->x, $this->y);
    }
}
