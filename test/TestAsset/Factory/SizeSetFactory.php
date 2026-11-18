<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Factory;

use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;

/**
 * Builds valid, cropped size sets for tests, varying only what a test cares
 * about.
 *
 * @internal
 */
final class SizeSetFactory
{
    /**
     * @param list<int> $widths
     * @param non-empty-list<Format> $formats
     * @param array<string, int> $quality
     */
    public static function make(
        string $handle = 'card',
        array $widths = [480, 960],
        ?string $ratio = null,
        array $formats = [Format::Avif, Format::Jpg],
        array $quality = [],
    ): SizeSet {
        return new SizeSet(
            $handle,
            $widths,
            sizes: '100vw',
            encoding: new Encoding(new QualityTable($quality), ...$formats),
            fit: new Fit(Mode::Crop, null === $ratio ? null : AspectRatio::fromString($ratio)),
        );
    }
}
