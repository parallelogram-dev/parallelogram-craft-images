<?php

declare(strict_types=1);

namespace parallelogram\images\processing\gd;

use GdImage;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\processing\Backdrop;
use parallelogram\images\sizing\Format;
use parallelogram\images\variants\Dimensions;

use function imagealphablending;
use function imageavif;
use function imagecopy;
use function imageinterlace;
use function imagejpeg;
use function imagepng;
use function imagesx;
use function imagesy;
use function imagewebp;
use function ob_get_clean;
use function ob_start;

/**
 * Encodes a GD image. JPEGs are flattened onto white (JPEG has no
 * transparency) and saved progressive; PNGs ignore quality, as PNG is
 * lossless.
 *
 * @internal
 */
final class GdEncoder
{
    private const int AVIF_SPEED = 6;

    private const int PNG_COMPRESSION = 6;

    /**
     * @throws GenerationFailedException
     */
    public static function encode(GdImage $image, Format $format, int $quality): string
    {
        if (Format::Jpg === $format) {
            $image = self::flattened($image);
        }

        ob_start();

        try {
            GdGuard::call("encode {$format->value}", static fn(): bool => self::write($image, $format, $quality));
        } finally {
            $bytes = (string) ob_get_clean();
        }

        return $bytes;
    }

    /**
     * @throws GenerationFailedException
     */
    private static function flattened(GdImage $image): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $canvas = GdCanvas::blank(new Dimensions($width, $height), Backdrop::White);

        GdGuard::call(
            'flatten the image',
            static fn(): bool => (
                imagealphablending($canvas, enable: true)
                && imagecopy(
                    $canvas,
                    $image,
                    dst_x: 0,
                    dst_y: 0,
                    src_x: 0,
                    src_y: 0,
                    src_width: $width,
                    src_height: $height,
                )
                && imageinterlace($canvas, enable: true)
            ),
        );

        return $canvas;
    }

    private static function write(GdImage $image, Format $format, int $quality): bool
    {
        if (Format::Jpg === $format) {
            return imagejpeg($image, file: null, quality: $quality);
        }

        if (Format::Png === $format) {
            return imagepng($image, file: null, quality: self::PNG_COMPRESSION);
        }

        if (Format::Webp === $format) {
            return imagewebp($image, file: null, quality: $quality);
        }

        return imageavif($image, file: null, quality: $quality, speed: self::AVIF_SPEED);
    }
}
