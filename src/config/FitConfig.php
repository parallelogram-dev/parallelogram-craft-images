<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\Mode;

/**
 * Reads the `mode` and `ratio` of a size set's config.
 *
 * @internal
 */
final class FitConfig
{
    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidSizeSetException When the mode or ratio cannot be used.
     */
    public static function fromArray(string $handle, array $config): Fit
    {
        $mode  = ConfigValues::string($config['mode'] ?? null);
        $ratio = ConfigValues::string($config['ratio'] ?? null);

        return new Fit(
            '' === $mode ? Mode::Crop : self::mode($handle, $mode),
            '' === $ratio ? null : AspectRatio::fromString($ratio),
        );
    }

    /**
     * @throws InvalidSizeSetException
     */
    private static function mode(string $handle, string $value): Mode
    {
        return (
            Mode::tryFrom($value)
                ?? throw new InvalidSizeSetException(
                    "“{$handle}” has an unknown mode “{$value}”. Use crop, fit, letterbox or stretch.",
                )
        );
    }
}
