<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SizeSet::class)]
#[CoversClass(Variant::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(Box::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[UsesClass(QualityTable::class)]
#[Group('unit')]
final class SizeSetTest extends TestCase
{
    /**
     * @return array<string, array{int, int, list<int>}>
     */
    public static function croppedWidthsProvider(): array
    {
        return [
            'source fills every width'   => [3000, 3000, [480, 960, 1440]],
            'source too narrow'          => [1000, 3000, [480, 960]],
            'source too short to crop'   => [3000, 600, [480]],
            'source smaller than ladder' => [300, 300, [300]],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHandleProvider(): array
    {
        return [
            'empty'          => [''],
            'leading digit'  => ['2up'],
            'contains space' => ['hero image'],
        ];
    }

    /**
     * @return array<string, array{list<int>, string}>
     */
    public static function invalidWidthsProvider(): array
    {
        return [
            'no widths' => [[], 'needs at least one width'],
            'zero'      => [[0, 480], 'has a width of 0px'],
            'too wide'  => [[480, 10_001], 'has a width of 10001px'],
        ];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('croppedWidthsProvider')]
    #[Test]
    public function dropsWidthsTheSourceCannotFill(int $sourceWidth, int $sourceHeight, array $expected): void
    {
        $sizeSet = SizeSetFactory::make(
            widths: [480, 960, 1440],
            ratio: '1:1',
        );

        static::assertSame($expected, $sizeSet->widthsFor($sourceWidth, $sourceHeight));
    }

    #[Test]
    public function listsVariantsByFormatThenWidth(): void
    {
        $sizeSet = SizeSetFactory::make(
            widths: [480, 960],
            ratio: '4:3',
            quality: ['avif' => 50],
        );

        static::assertEquals(
            [
                new Variant('card', new Box(480, 360, Mode::Crop), Format::Avif, 50),
                new Variant('card', new Box(960, 720, Mode::Crop), Format::Avif, 50),
                new Variant('card', new Box(480, 360, Mode::Crop), Format::Jpg, 75),
                new Variant('card', new Box(960, 720, Mode::Crop), Format::Jpg, 75),
            ],
            $sizeSet->variantsFor(2000, sourceHeight: 2000, siteQuality: new QualityTable(['avif' => 60, 'jpg' => 75])),
        );
    }

    #[DataProvider('invalidHandleProvider')]
    #[Test]
    public function rejectsAnInvalidHandle(string $handle): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('is not a valid handle');

        SizeSetFactory::make(handle: $handle);
    }

    #[Test]
    public function rejectsBlankSizes(): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('“card” needs a sizes value, such as 100vw.');

        new SizeSet('card', [480], sizes: '  ', encoding: new Encoding(new QualityTable(), Format::Jpg));
    }

    /**
     * @param list<int> $widths
     */
    #[DataProvider('invalidWidthsProvider')]
    #[Test]
    public function rejectsUnusableWidths(array $widths, string $message): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage($message);

        SizeSetFactory::make(widths: $widths);
    }

    #[Test]
    public function sortsWidthsAndRemovesRepeats(): void
    {
        static::assertSame([480, 960, 1440], SizeSetFactory::make(widths: [960, 480, 1440, 480])->widths);
    }

    #[Test]
    public function trimsSizes(): void
    {
        $sizeSet = new SizeSet('card', [480], sizes: ' 50vw ', encoding: new Encoding(new QualityTable(), Format::Jpg));

        static::assertSame('50vw', $sizeSet->sizes);
    }

    #[Test]
    public function usesEveryWidthWhenTheRatioIsFitted(): void
    {
        $sizeSet = new SizeSet(
            'card',
            [480, 960],
            sizes: '100vw',
            encoding: new Encoding(new QualityTable(), Format::Jpg),
            fit: new Fit(Mode::Fit, new AspectRatio(1, 1)),
        );

        static::assertSame([480, 960], $sizeSet->widthsFor(1000, sourceHeight: 200));
    }
}
