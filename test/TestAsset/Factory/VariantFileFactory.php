<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Factory;

use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\variants\VariantFile;

/**
 * Builds variant files with short, readable paths: `{width}.{ext}`.
 *
 * @internal
 */
final class VariantFileFactory
{
    public static function make(
        int $width,
        Format $format = Format::Jpg,
        ?int $height = null,
        Mode $mode = Mode::Crop,
    ): VariantFile {
        return new VariantFile(
            new Variant('card', new Box($width, $height, $mode), $format, quality: 80),
            hash: 'abc',
            path: "{$width}.{$format->value}",
        );
    }
}
