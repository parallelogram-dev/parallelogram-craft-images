<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Factory;

use GdImage;
use LogicException;

use function imagecolorallocate;
use function imagecreate;
use function imagecreatetruecolor;
use function imagefilledrectangle;
use function imagegif;
use function imagejpeg;
use function imagepng;
use function intdiv;
use function ob_get_clean;
use function ob_start;
use function pack;
use function strlen;
use function substr;

/**
 * Builds small test images with GD in memory, for the GD generator's tests.
 *
 * @internal
 */
final class GdImageFactory
{
    private const array BLUE = [0, 0, 255];

    private const array RED = [255, 0, 0];

    private const array WHITE = [255, 255, 255];

    /**
     * A 40×20 JPEG, white, with a red square top-left and a blue square
     * top-right, so every rotation and flip leaves a distinct corner pattern.
     * An EXIF orientation tag is written in by hand when one is given.
     */
    public static function cornersJpeg(?int $orientation = null): string
    {
        $image = self::checked(imagecreatetruecolor(40, height: 20));
        self::paint($image, [0, 0, 39, 19], self::WHITE);
        self::paint($image, [0, 0, 9, 9], self::RED);
        self::paint($image, [30, 0, 39, 9], self::BLUE);

        ob_start();
        imagejpeg($image, file: null, quality: 100);
        $jpeg = (string) ob_get_clean();

        if (null === $orientation) {
            return $jpeg;
        }

        $tag   = 0x0112;
        $short = 3;
        $count = 1;
        $none  = 0;
        $ifd   = pack('vvVvv', $tag, $short, $count, $orientation, $none);
        $app1  = "Exif\0\0II*\0" . pack('V', 8) . pack('v', $count) . $ifd . pack('V', $none);

        return (
            substr($jpeg, offset: 0, length: 2)
                . "\xFF\xE1"
                . pack('n', strlen($app1) + 2)
                . $app1
                . substr($jpeg, offset: 2)
        );
    }

    /**
     * A palette (non-truecolour) GIF in one colour.
     */
    public static function paletteGif(int $width, int $height): string
    {
        $image = self::checked(imagecreate($width, $height));
        self::colour($image, [0, 128, 0]);

        ob_start();
        imagegif($image);

        return (string) ob_get_clean();
    }

    /**
     * A PNG whose left half is red and right half is blue.
     */
    public static function redLeftBlueRight(int $width, int $height): string
    {
        $image = self::checked(imagecreatetruecolor($width, $height));
        self::paint($image, [0, 0, $width - 1, $height - 1], self::RED);
        self::paint($image, [intdiv($width, num2: 2), 0, $width - 1, $height - 1], self::BLUE);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private static function checked(GdImage|false $image): GdImage
    {
        if (false === $image) {
            throw new LogicException('GD could not create an image.');
        }

        return $image;
    }

    /**
     * @param array{int, int, int} $rgb
     */
    private static function colour(GdImage $image, array $rgb): int
    {
        [$red, $green, $blue] = $rgb;
        $colour = imagecolorallocate($image, $red, $green, $blue);

        if (false === $colour) {
            throw new LogicException('GD could not allocate a colour.');
        }

        return $colour;
    }

    /**
     * @param array{int, int, int, int} $area Left, top, right, bottom.
     * @param array{int, int, int} $rgb
     */
    private static function paint(GdImage $image, array $area, array $rgb): void
    {
        [$left, $top, $right, $bottom] = $area;

        imagefilledrectangle($image, $left, $top, $right, $bottom, self::colour($image, $rgb));
    }
}
