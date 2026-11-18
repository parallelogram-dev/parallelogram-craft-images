<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\variants;

use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SourceImage::class)]
#[CoversClass(Dimensions::class)]
#[CoversClass(FocalPoint::class)]
#[CoversClass(Processing::class)]
#[CoversClass(InvalidVariantException::class)]
#[Group('unit')]
final class SourceImageTest extends TestCase
{
    /**
     * @return array<string, array{int, int}>
     */
    public static function badDimensionsProvider(): array
    {
        return [
            'zero width'  => [0, 100],
            'zero height' => [100, 0],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function basenameProvider(): array
    {
        return [
            'simple'       => ['harbour.jpg', 'harbour'],
            'dots in name' => ['harbour.dawn.v2.jpg', 'harbour.dawn.v2'],
            'no extension' => ['harbour', 'harbour'],
            'slashes'      => ['a/b\\c.png', 'b-c'],
            'leading dots' => ['..hidden.jpg', 'hidden'],
        ];
    }

    #[Test]
    public function centresTheFocalPointByDefault(): void
    {
        static::assertSame('0.5000,0.5000', SourceImageFactory::make()->focalPoint->toString());
    }

    #[Test]
    public function clampsFocalPointsToTheImage(): void
    {
        static::assertSame('0.0000,1.0000', (new FocalPoint(-0.2, y: 1.7))->toString());
    }

    #[Test]
    public function describesProcessingForHashing(): void
    {
        static::assertSame(
            'orient:0,srgb:1,sharpen:0.75',
            (new Processing(false, convertToSrgb: true, sharpenAmount: 0.75))->toString(),
        );
    }

    #[DataProvider('basenameProvider')]
    #[Test]
    public function makesASafeBasename(string $filename, string $expected): void
    {
        static::assertSame($expected, SourceImageFactory::make(filename: $filename)->basename());
    }

    #[Test]
    public function rejectsAFileNameWithNothingLeft(): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('has no usable file name');

        SourceImageFactory::make(filename: '...');
    }

    #[DataProvider('badDimensionsProvider')]
    #[Test]
    public function rejectsImagesWithoutDimensions(int $width, int $height): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('An image needs a width and height of at least 1px to be resized.');

        new Dimensions($width, $height);
    }

    #[Test]
    public function rejectsSomethingThatIsNotAUid(): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('“42” is not an asset UID.');

        new SourceImage('42', 'harbour.jpg', new Dimensions(10, 10), modifiedAt: 0);
    }
}
