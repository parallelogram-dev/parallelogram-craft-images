<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use function intdiv;
use function max;
use function min;
use function preg_match;
use function round;
use function trim;

/**
 * A width-to-height ratio such as 4:3.
 *
 * @api
 */
final readonly class AspectRatio
{
    /**
     * @throws InvalidSizeSetException When either side is less than 1.
     */
    public function __construct(
        public int $width,
        public int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidSizeSetException('An aspect ratio needs two whole numbers above zero, such as 4:3.');
        }
    }

    /**
     * Reads `4:3`, `4/3`, `4x3` or `4×3`, with optional spaces.
     *
     * @throws InvalidSizeSetException When the value is not a ratio.
     */
    public static function fromString(string $value): self
    {
        $matches = [];

        if (1 !== preg_match('/^\s*(\d+)\s*[:\/xX×]\s*(\d+)\s*$/u', $value, $matches)) {
            throw new InvalidSizeSetException('“' . trim($value) . '” is not an aspect ratio. Use a form like 4:3.');
        }

        return new self((int) ($matches[1] ?? 0), (int) ($matches[2] ?? 0));
    }

    /**
     * The ratio of a width and height in lowest terms, e.g. 1600×1200 is 4:3.
     *
     * @throws InvalidSizeSetException When either side is less than 1.
     */
    public static function of(int $width, int $height): self
    {
        if ($width < 1 || $height < 1) {
            return new self($width, $height);
        }

        $divisor = self::greatestCommonDivisor($width, $height);

        return new self(intdiv($width, $divisor), intdiv($height, $divisor));
    }

    private static function greatestCommonDivisor(int $a, int $b): int
    {
        return 0 === $b ? $a : self::greatestCommonDivisor($b, $a % $b);
    }

    /**
     * The height that matches a width, rounded to the nearest pixel.
     */
    public function heightFor(int $width): int
    {
        return max(1, (int) round(($width * $this->height) / $this->width));
    }

    public function toString(): string
    {
        return "{$this->width}:{$this->height}";
    }

    /**
     * The widest box of this ratio that fits inside the given dimensions.
     */
    public function widestWithin(int $width, int $height): int
    {
        return max(1, min($width, (int) (($height * $this->width) / $this->height)));
    }
}
