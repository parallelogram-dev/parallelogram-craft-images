<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use function array_key_exists;

/**
 * Encoder quality per format (1–100), falling back to each format's default.
 *
 * @api
 */
final readonly class QualityTable
{
    /**
     * @var array<string, int>
     */
    private array $values;

    /**
     * @param array<string, int> $values Keyed by format value, e.g. `['avif' => 55]`.
     *
     * @throws InvalidSizeSetException When a key is not a format or a value is outside 1–100.
     */
    public function __construct(array $values = [])
    {
        $checked = [];

        foreach ($values as $key => $quality) {
            $format = Format::tryFrom($key);

            if (null === $format) {
                throw new InvalidSizeSetException("“{$key}” is not an image format.");
            }

            if ($quality < 1 || $quality > 100) {
                throw new InvalidSizeSetException("{$format->name} quality must be a whole number from 1 to 100.");
            }

            $checked[$key] = $quality;
        }

        $this->values = $checked;
    }

    public function for(Format $format): int
    {
        return $this->values[$format->value] ?? $format->defaultQuality();
    }

    public function has(Format $format): bool
    {
        return array_key_exists($format->value, $this->values);
    }

    /**
     * This table with the given values taking precedence.
     */
    public function overriddenBy(self $overrides): self
    {
        $merged = $this->values;

        foreach (Format::cases() as $format) {
            if (! $overrides->has($format)) {
                continue;
            }

            $merged[$format->value] = $overrides->for($format);
        }

        return new self($merged);
    }

    /**
     * Only the values set explicitly, for saving.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
