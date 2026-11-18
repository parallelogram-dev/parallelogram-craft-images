<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Factory;

use parallelogram\images\picture\FormatSource;
use parallelogram\images\picture\PictureSources;
use parallelogram\images\sizing\Format;
use parallelogram\images\variants\Dimensions;

/**
 * Builds picture sources with an AVIF and a JPG `srcset` of two widths,
 * named after the size set so art-directed ones can be told apart.
 *
 * @internal
 */
final class PictureSourcesFactory
{
    public static function make(string $sizeSet = 'card', string $sizes = '100vw'): PictureSources
    {
        $jpg = new FormatSource(
            Format::Jpg,
            "/{$sizeSet}-480.jpg 480w, /{$sizeSet}-960.jpg 960w",
            "/{$sizeSet}-960.jpg",
        );

        return new PictureSources(
            $sizeSet,
            [
                new FormatSource(
                    Format::Avif,
                    "/{$sizeSet}-480.avif 480w, /{$sizeSet}-960.avif 960w",
                    "/{$sizeSet}-960.avif",
                ),
                $jpg,
            ],
            $jpg,
            $sizes,
            new Dimensions(960, 720),
        );
    }
}
