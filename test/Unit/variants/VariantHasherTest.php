<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\variants;

use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariantHasher::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(Processing::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[Group('unit')]
final class VariantHasherTest extends TestCase
{
    /**
     * Each case changes one input that alters the file's pixels.
     *
     * @return array<string, array{VariantHasher, SourceImage, Variant}>
     */
    public static function changedInputProvider(): array
    {
        $hasher = new VariantHasher('secret', new Processing());

        return [
            'file replaced'     => [$hasher, SourceImageFactory::make(modifiedAt: 2), self::variant()],
            'focal point moved' => [
                $hasher,
                SourceImageFactory::make(focalPoint: new FocalPoint(0.2, y: 0.5)),
                self::variant(),
            ],
            'wider'             => [$hasher, SourceImageFactory::make(), self::variant(width: 961)],
            'cropped'           => [$hasher, SourceImageFactory::make(), self::variant(height: 600)],
            'fitted'            => [$hasher, SourceImageFactory::make(), self::variant(mode: Mode::Fit)],
            'other format'      => [$hasher, SourceImageFactory::make(), self::variant(format: Format::Jpg)],
            'other quality'     => [$hasher, SourceImageFactory::make(), self::variant(quality: 61)],
            'other processing'  => [
                new VariantHasher('secret', new Processing(autoOrient: false)),
                SourceImageFactory::make(),
                self::variant(),
            ],
            'other secret'      => [
                new VariantHasher('other', new Processing()),
                SourceImageFactory::make(),
                self::variant(),
            ],
        ];
    }

    private static function variant(
        int $width = 960,
        ?int $height = null,
        Mode $mode = Mode::Crop,
        Format $format = Format::Avif,
        int $quality = 60,
    ): Variant {
        return new Variant('card', new Box($width, $height, $mode), $format, $quality);
    }

    #[DataProvider('changedInputProvider')]
    #[Test]
    public function changesWhenAnythingAffectingThePixelsChanges(
        VariantHasher $hasher,
        SourceImage $source,
        Variant $variant,
    ): void {
        $baseline = (new VariantHasher('secret', new Processing()))->hash(SourceImageFactory::make(), self::variant());

        static::assertNotSame($baseline, $hasher->hash($source, $variant));
    }

    #[Test]
    public function ignoresWhichSizeSetAskedForTheFile(): void
    {
        $hasher = new VariantHasher('secret', new Processing());
        $source = SourceImageFactory::make();

        $box = new Box(960, null, Mode::Crop);

        static::assertSame(
            $hasher->hash($source, new Variant('card', $box, Format::Avif, quality: 60)),
            $hasher->hash($source, new Variant('tile', $box, Format::Avif, quality: 60)),
        );
    }

    #[Test]
    public function isSixteenLowercaseHexCharacters(): void
    {
        static::assertMatchesRegularExpression(
            '/^[0-9a-f]{16}$/',
            (new VariantHasher('secret', new Processing()))->hash(SourceImageFactory::make(), self::variant()),
        );
    }

    #[Test]
    public function needsASecret(): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('Image variants need a secret. Set P_IMAGES_SECRET in .env.');

        new VariantHasher('', new Processing());
    }
}
