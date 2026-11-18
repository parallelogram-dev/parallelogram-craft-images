<?php

declare(strict_types=1);

namespace parallelogram\images\test\Integration\processing;

use Imagick;
use ImagickPixel;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\processing\ImagickGenerator;
use parallelogram\images\processing\Rect;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
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

/**
 * Runs the real ImageMagick extension on small in-memory images.
 *
 * @internal
 */
#[CoversClass(ImagickGenerator::class)]
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
final class ImagickGeneratorTest extends TestCase
{
    /**
     * @return array<string, array{float, string}>
     */
    public static function focalCropProvider(): array
    {
        return [
            'focal point on the left'  => [0.1, 'red'],
            'focal point on the right' => [0.9, 'blue'],
        ];
    }

    /**
     * @return array<string, array{Format, string}>
     */
    public static function formatProvider(): array
    {
        return [
            'avif' => [Format::Avif, 'AVIF'],
            'jpg'  => [Format::Jpg, 'JPEG'],
            'png'  => [Format::Png, 'PNG'],
            'webp' => [Format::Webp, 'WEBP'],
        ];
    }

    /**
     * @return array{int, int}
     */
    private static function dimensionsOf(string $blob): array
    {
        $image = new Imagick();
        $image->pingImageBlob($blob);

        return [$image->getImageWidth(), $image->getImageHeight()];
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
        $source = SourceImageFactory::make();

        return (new ImagickGenerator(new StaticSourceFile($original), new Processing()))->generate($source, self::file(
            $box,
            $format,
        ));
    }

    private static function pixel(string $blob, int $x, int $y): ImagickPixel
    {
        $image = new Imagick();
        $image->readImageBlob($blob);

        return $image->getImagePixelColor($x, $y);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function autoOrientsByExifData(): void
    {
        $output = self::generate(
            ImageFactory::withOrientation(100, height: 50, orientation: 6),
            new Box(25, null, Mode::Crop),
        );

        static::assertSame([25, 50], self::dimensionsOf($output));
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function convertsCmykImagesToSrgb(): void
    {
        $image = new Imagick();
        $image->readImageBlob(self::generate(
            ImageFactory::cmyk(20, height: 10),
            new Box(10, null, Mode::Crop),
            Format::Jpg,
        ));

        static::assertSame(Imagick::COLORSPACE_SRGB, $image->getImageColorspace());
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function convertsProfiledImagesToSrgbAndStripsMetadata(): void
    {
        $output = self::generate(
            ImageFactory::withIccProfile((string) file_get_contents(ImagickGenerator::SRGB_PROFILE)),
            new Box(10, null, Mode::Crop),
            Format::Png,
        );

        $image = new Imagick();
        $image->readImageBlob($output);

        static::assertSame([Imagick::COLORSPACE_SRGB, []], [
            $image->getImageColorspace(),
            $image->getImageProfiles('*', include_values: false),
        ]);
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('focalCropProvider')]
    #[Test]
    public function cropsAroundTheFocalPoint(float $focalX, string $colour): void
    {
        $output = (new ImagickGenerator(
            new StaticSourceFile(ImageFactory::redLeftBlueRight(200, height: 100)),
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

        static::assertTrue(self::pixel($output, x: 25, y: 25)->isPixelSimilar(new ImagickPixel($colour), fuzz: 0.1));
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('formatProvider')]
    #[Test]
    public function encodesEachFormat(Format $format, string $expected): void
    {
        $image = new Imagick();
        $image->pingImageBlob(self::generate(
            ImageFactory::solid(40, height: 20),
            new Box(20, null, Mode::Crop),
            $format,
        ));

        static::assertSame($expected, $image->getImageFormat());
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function leavesColourAndMetadataAloneWhenNotConverting(): void
    {
        $profile = (string) file_get_contents(ImagickGenerator::SRGB_PROFILE);
        $output  = (new ImagickGenerator(
            new StaticSourceFile(ImageFactory::withIccProfile($profile)),
            new Processing(
                autoOrient: false,
                convertToSrgb: false,
                sharpenAmount: 0.0,
            ),
        ))->generate(
            SourceImageFactory::make(
                width: 20,
                height: 10,
            ),
            self::file(new Box(10, null, Mode::Crop), Format::Jpg),
        );

        $image = new Imagick();
        $image->readImageBlob($output);

        static::assertCount(1, $image->getImageProfiles('icc', include_values: false));
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function padsLetterboxedJpegsWithWhite(): void
    {
        $output = self::generate(
            ImageFactory::solid(200, height: 100),
            new Box(100, 100, Mode::Letterbox),
            Format::Jpg,
        );

        static::assertSame(
            [[100, 100], true],
            [
                self::dimensionsOf($output),
                self::pixel($output, x: 50, y: 2)->isPixelSimilar(new ImagickPixel('white'), fuzz: 0.05),
            ],
        );
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function padsLetterboxedPngsWithTransparency(): void
    {
        $output = self::generate(
            ImageFactory::solid(200, height: 100),
            new Box(100, 100, Mode::Letterbox),
            Format::Png,
        );

        static::assertSame([[100, 100], 0.0], [
            self::dimensionsOf($output),
            self::pixel($output, x: 50, y: 2)->getColorValue(Imagick::COLOR_ALPHA),
        ]);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function refusesFilesImageMagickCannotRead(): void
    {
        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('ImageMagick couldn\'t make');

        self::generate('not an image', new Box(10, null, Mode::Crop));
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function resizesToTheVariantsWidth(): void
    {
        static::assertSame(
            [40, 20],
            self::dimensionsOf(self::generate(ImageFactory::solid(400, height: 200), new Box(40, null, Mode::Crop))),
        );
    }
}
