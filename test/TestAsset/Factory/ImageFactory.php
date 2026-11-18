<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Factory;

use Imagick;
use ImagickDraw;
use ImagickPixel;

use function pack;
use function strlen;
use function substr;

/**
 * Builds small test images in memory, so image tests never touch the disk.
 *
 * @internal
 */
final class ImageFactory
{
    /**
     * A CMYK JPEG, as print workflows often produce.
     */
    public static function cmyk(int $width, int $height): string
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('cmyk(0,100,100,0)'));
        $image->setImageColorspace(Imagick::COLORSPACE_CMYK);
        $image->setImageFormat('jpeg');

        return $image->getImageBlob();
    }

    /**
     * A PNG of the same pseudo-random pixels every time, which an encoder has
     * to work at: how hard it tries shows in the file it writes.
     */
    public static function noise(int $width, int $height): string
    {
        $state  = 7;
        $pixels = [];

        for ($index = 0; $index < ($width * $height * 3); ++$index) {
            $state    = (($state * 1_103_515_245) + 12_345) & 0x7FFF_FFFF;
            $pixels[] = ($state >> 16) & 0xFF;
        }

        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('black'));
        $image->importImagePixels(
            x: 0,
            y: 0,
            width: $width,
            height: $height,
            map: 'RGB',
            pixelstorage: Imagick::PIXEL_CHAR,
            pixels: $pixels,
        );
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }

    /**
     * A PNG whose left half is red and right half is blue, for checking which
     * part of an image a crop kept.
     */
    public static function redLeftBlueRight(int $width, int $height): string
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('red'));

        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('blue'));
        $draw->rectangle($width / 2, top_left_y: 0, bottom_right_x: $width, bottom_right_y: $height);
        $image->drawImage($draw);
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }

    /**
     * A plain image in one colour.
     */
    public static function solid(int $width, int $height, string $colour = 'red', string $format = 'png'): string
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel($colour));
        $image->setImageFormat($format);

        return $image->getImageBlob();
    }

    /**
     * A fully transparent PNG.
     */
    public static function transparentPng(int $width, int $height): string
    {
        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('transparent'));
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }

    /**
     * A red JPEG carrying an ICC profile.
     */
    public static function withIccProfile(string $profile): string
    {
        $image = new Imagick();
        $image->newImage(
            columns: 20,
            rows: 10,
            background_color: new ImagickPixel('red'),
        );
        $image->setImageFormat('jpeg');
        $image->profileImage('icc', $profile);

        return $image->getImageBlob();
    }

    /**
     * A JPEG with an EXIF orientation tag, written by hand because ImageMagick
     * only writes the tag into images that already carry EXIF data.
     */
    public static function withOrientation(int $width, int $height, int $orientation): string
    {
        $jpeg = self::solid($width, $height, colour: 'red', format: 'jpeg');

        $firstIfdOffset = 8;
        $entryCount     = 1;
        $orientationTag = 0x0112;
        $shortType      = 3;
        $valueCount     = 1;
        $noNextIfd      = 0;

        $ifd  = pack('vvVvv', $orientationTag, $shortType, $valueCount, $orientation, $noNextIfd);
        $tiff = "II*\0" . pack('V', $firstIfdOffset) . pack('v', $entryCount) . $ifd . pack('V', $noNextIfd);
        $app1 = "Exif\0\0{$tiff}";

        return (
            substr($jpeg, offset: 0, length: 2)
                . "\xFF\xE1"
                . pack('n', strlen($app1) + 2)
                . $app1
                . substr($jpeg, offset: 2)
        );
    }
}
