<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\AspectRatio;

use function array_filter;
use function is_array;
use function is_int;

/**
 * One variant row from the prototype: a width, a height, or both. Missing or
 * non-numeric values read as zero.
 *
 * @internal
 */
final readonly class PrototypeVariant
{
    public function __construct(
        public int $width,
        public int $height,
    ) {}

    /**
     * @param array<array-key, mixed> $matrix
     *
     * @return list<self>
     */
    public static function allIn(array $matrix): array
    {
        if (! is_array($matrix['variants'] ?? null)) {
            return [];
        }

        $variants = [];

        foreach (array_filter($matrix['variants'], is_array(...)) as $row) {
            $variants[] = self::fromArray($row);
        }

        return $variants;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(self::whole($row['width'] ?? 0), self::whole($row['height'] ?? 0));
    }

    private static function whole(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    /**
     * Its shape in lowest terms, or an empty string when it had no height.
     */
    public function ratio(): string
    {
        return $this->height < 1 ? '' : AspectRatio::of($this->width, $this->height)->toString();
    }
}
