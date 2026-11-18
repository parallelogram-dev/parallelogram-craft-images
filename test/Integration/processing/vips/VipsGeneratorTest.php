<?php

declare(strict_types=1);

namespace parallelogram\images\test\Integration\processing\vips;

use GdImage;
use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\Image;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\processing\Backdrop;
use parallelogram\images\processing\ImagickGenerator;
use parallelogram\images\processing\Rect;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\processing\vips\VipsCanvas;
use parallelogram\images\processing\vips\VipsGenerator;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\GdImageFactory;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\test\TestAsset\Fake\StaticSourceFile;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function getimagesizefromstring;
use function imagecolorat;
use function imagecreatefromstring;
use function imagesx;
use function imagesy;
use function implode;

/**
 * Runs real libvips, through php-vips and FFI, on small in-memory images.
 *
 * @internal
 */
#[CoversClass(VipsGenerator::class)]
#[CoversClass(VipsCanvas::class)]
#[UsesClass(Backdrop::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(GenerationFailedException::class)]
#[UsesClass(Processing::class)]
#[UsesClass(Rect::class)]
#[UsesClass(ResizePlan::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[Group('integration')]
final class VipsGeneratorTest extends TestCase
{
    /**
     * @return array<string, array{float, string}>
     */
    public static function focalCropProvider(): array
    {
        return [
            'focal point on the left'  => [0.1, 'R'],
            'focal point on the right' => [0.9, 'B'],
        ];
    }

    /**
     * @return array<string, array{Format, string}>
     */
    public static function formatProvider(): array
    {
        return [
            'avif' => [Format::Avif, 'image/avif'],
            'jpg'  => [Format::Jpg, 'image/jpeg'],
            'png'  => [Format::Png, 'image/png'],
            'webp' => [Format::Webp, 'image/webp'],
        ];
    }

    /**
     * Corner patterns after ImageMagick's auto-orient, which libvips must match.
     *
     * @return array<string, array{int, string}>
     */
    public static function orientationProvider(): array
    {
        return [
            'upright'                => [1, '40x20 RBWW'],
            'mirrored'               => [2, '40x20 BRWW'],
            'upside down'            => [3, '40x20 WWBR'],
            'mirrored upside down'   => [4, '40x20 WWRB'],
            'mirrored, turned left'  => [5, '20x40 RWBW'],
            'turned right'           => [6, '20x40 WRWB'],
            'mirrored, turned right' => [7, '20x40 WBWR'],
            'turned left'            => [8, '20x40 BWRW'],
        ];
    }

    private static function colourAt(GdImage $image, int $x, int $y): string
    {
        $colour = imagecolorat($image, $x, $y);
        $red    = ($colour >> 16) & 0xFF;
        $green  = ($colour >> 8) & 0xFF;
        $blue   = $colour & 0xFF;

        if ($red > 200 && $green < 80 && $blue < 80) {
            return 'R';
        }

        return $blue > 200 && $red < 80 ? 'B' : 'W';
    }

    private static function corners(string $blob): string
    {
        $image  = self::decode($blob);
        $width  = imagesx($image);
        $height = imagesy($image);
        $marks  = [];

        foreach ([[2, 2], [$width - 3, 2], [2, $height - 3], [$width - 3, $height - 3]] as [$x, $y]) {
            $marks[] = self::colourAt($image, $x, $y);
        }

        return "{$width}x{$height} " . implode('', $marks);
    }

    private static function decode(string $blob): GdImage
    {
        $image = imagecreatefromstring($blob);

        return false === $image ? static::fail('The output could not be decoded.') : $image;
    }

    private static function file(Box $box, Format $format): VariantFile
    {
        return new VariantFile(
            new Variant('card', $box, $format, quality: 80),
            '0123456789abcdef',
            "asset/variant/x.{$format->value}",
        );
    }

    /**
     * @throws GenerationFailedException
     */
    private static function generate(string $original, Box $box, Format $format = Format::Png): string
    {
        return (new VipsGenerator(
            new StaticSourceFile($original),
            new Processing(),
        ))->generate(SourceImageFactory::make(), self::file($box, $format));
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('orientationProvider')]
    #[Test]
    public function appliesEveryExifOrientation(int $orientation, string $corners): void
    {
        static::assertSame(
            $corners,
            self::corners(self::generate(GdImageFactory::cornersJpeg($orientation), new Box(1000, null, Mode::Crop))),
        );
    }

    /**
     * @throws GenerationFailedException
     * @throws VipsException
     */
    #[Test]
    public function convertsCmykImagesToSrgb(): void
    {
        $output = self::generate(ImageFactory::cmyk(20, height: 10), new Box(10, null, Mode::Crop), Format::Jpg);

        static::assertSame('srgb', Image::newFromBuffer($output)->interpretation);
    }

    /**
     * @throws GenerationFailedException
     * @throws VipsException
     */
    #[Test]
    public function convertsProfiledImagesToSrgbAndStripsTheProfile(): void
    {
        $profiled = ImageFactory::withIccProfile((string) file_get_contents(ImagickGenerator::SRGB_PROFILE));
        $output   = self::generate($profiled, new Box(10, null, Mode::Crop), Format::Jpg);

        static::assertSame(0, Image::newFromBuffer($output)->typeof('icc-profile-data'));
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('focalCropProvider')]
    #[Test]
    public function cropsAroundTheFocalPoint(float $focalX, string $colour): void
    {
        $output = (new VipsGenerator(
            new StaticSourceFile(GdImageFactory::redLeftBlueRight(200, height: 100)),
            new Processing(),
        ))
            ->generate(
                SourceImageFactory::make(
                    width: 200,
                    height: 100,
                    focalPoint: new FocalPoint($focalX, y: 0.5),
                ),
                self::file(new Box(50, 50, Mode::Crop), Format::Png),
            );

        static::assertSame($colour, self::colourAt(self::decode($output), x: 25, y: 25));
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('formatProvider')]
    #[Test]
    public function encodesEachFormat(Format $format, string $mimeType): void
    {
        $info = getimagesizefromstring(self::generate(
            GdImageFactory::cornersJpeg(),
            new Box(20, null, Mode::Crop),
            $format,
        ));

        static::assertSame($mimeType, $info['mime'] ?? null);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function flattensTransparencyOntoWhiteForJpeg(): void
    {
        $output = self::generate(
            ImageFactory::transparentPng(20, height: 10),
            new Box(20, null, Mode::Crop),
            Format::Jpg,
        );

        static::assertSame('W', self::colourAt(self::decode($output), x: 5, y: 5));
    }

    /**
     * @throws GenerationFailedException
     * @throws VipsException
     */
    #[Test]
    public function keepsOrientationAndProfileWhenProcessingIsOff(): void
    {
        $profiled = ImageFactory::withIccProfile((string) file_get_contents(ImagickGenerator::SRGB_PROFILE));
        $off      = new Processing(
            autoOrient: false,
            convertToSrgb: false,
            sharpenAmount: 0.0,
        );

        $profile = (new VipsGenerator(new StaticSourceFile($profiled), $off))
            ->generate(SourceImageFactory::make(), self::file(new Box(10, null, Mode::Crop), Format::Jpg));
        $oriented = (new VipsGenerator(new StaticSourceFile(GdImageFactory::cornersJpeg(6)), $off))
            ->generate(SourceImageFactory::make(), self::file(new Box(1000, null, Mode::Crop), Format::Png));

        static::assertSame(
            [true, '40x20 RBWW'],
            [0 !== Image::newFromBuffer($profile)->typeof('icc-profile-data'), self::corners($oriented)],
        );
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function padsLetterboxedJpegsWithWhite(): void
    {
        $image = self::decode(self::generate(
            GdImageFactory::redLeftBlueRight(200, height: 100),
            new Box(100, 100, Mode::Letterbox),
            Format::Jpg,
        ));

        static::assertSame([[100, 100], 'W'], [
            [imagesx($image), imagesy($image)],
            self::colourAt($image, x: 50, y: 2),
        ]);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function padsLetterboxedPngsWithTransparency(): void
    {
        $image = self::decode(self::generate(
            GdImageFactory::redLeftBlueRight(200, height: 100),
            new Box(100, 100, Mode::Letterbox),
            Format::Png,
        ));

        static::assertSame(127, (imagecolorat($image, x: 50, y: 2) >> 24) & 0x7F);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function readsPaletteImages(): void
    {
        $image = self::decode(self::generate(
            GdImageFactory::paletteGif(40, height: 20),
            new Box(20, null, Mode::Crop),
        ));

        static::assertSame([20, 10], [imagesx($image), imagesy($image)]);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function refusesFilesLibvipsCannotRead(): void
    {
        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('libvips couldn\'t make');

        self::generate('not an image', new Box(10, null, Mode::Crop));
    }
}
