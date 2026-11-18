<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\picture;

use parallelogram\images\picture\FormatSource;
use parallelogram\images\picture\PictureSources;
use parallelogram\images\picture\PictureSourcesBuilder;
use parallelogram\images\processing\Rect;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\test\TestAsset\Factory\VariantFileFactory;
use parallelogram\images\test\TestAsset\Fake\CdnLinks;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * @internal
 */
#[CoversClass(PictureSourcesBuilder::class)]
#[CoversClass(PictureSources::class)]
#[CoversClass(FormatSource::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(Rect::class)]
#[UsesClass(ResizePlan::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[Group('unit')]
final class PictureSourcesBuilderTest extends TestCase
{
    /**
     * @param list<VariantFile> $files
     */
    private static function build(array $files, ?SizeSet $sizeSet = null, ?SourceImage $source = null): PictureSources
    {
        return PictureSourcesBuilder::build(
            $source ?? SourceImageFactory::make(),
            $sizeSet ?? SizeSetFactory::make(),
            $files,
            new CdnLinks(),
        );
    }

    #[Test]
    public function describesFittedImagesByTheWidthTheyReallyHave(): void
    {
        $sources = self::build(
            [VariantFileFactory::make(800, height: 400, mode: Mode::Fit)],
            SizeSetFactory::make(formats: [Format::Jpg]),
            SourceImageFactory::make(
                width: 1000,
                height: 2000,
            ),
        );

        static::assertSame(
            ['https://cdn.test/800.jpg 200w', 200, 400],
            [$sources->fallback->srcset, $sources->size->width, $sources->size->height],
        );
    }

    #[Test]
    public function givesLetterboxedImagesTheirBoxSize(): void
    {
        $sources = self::build(
            [VariantFileFactory::make(800, height: 400, mode: Mode::Letterbox)],
            SizeSetFactory::make(formats: [Format::Jpg]),
            SourceImageFactory::make(
                width: 1000,
                height: 2000,
            ),
        );

        static::assertSame([800, 400], [$sources->size->width, $sources->size->height]);
    }

    #[Test]
    public function listsEachFormatInPreferenceOrderWithTheFallbackSizedByItsWidestFile(): void
    {
        $sources = self::build([
            VariantFileFactory::make(480, Format::Avif),
            VariantFileFactory::make(960, Format::Avif),
            VariantFileFactory::make(480),
            VariantFileFactory::make(960),
        ]);

        static::assertEquals(
            new PictureSources(
                'card',
                [
                    new FormatSource(
                        Format::Avif,
                        'https://cdn.test/480.avif 480w, https://cdn.test/960.avif 960w',
                        'https://cdn.test/960.avif',
                    ),
                    new FormatSource(
                        Format::Jpg,
                        'https://cdn.test/480.jpg 480w, https://cdn.test/960.jpg 960w',
                        'https://cdn.test/960.jpg',
                    ),
                ],
                new FormatSource(
                    Format::Jpg,
                    'https://cdn.test/480.jpg 480w, https://cdn.test/960.jpg 960w',
                    'https://cdn.test/960.jpg',
                ),
                '100vw',
                new Dimensions(960, 720),
            ),
            $sources,
        );
    }

    #[Test]
    public function readsOneFormatsSrcsetForTemplates(): void
    {
        $sources = self::build([VariantFileFactory::make(480, Format::Avif), VariantFileFactory::make(480)]);

        static::assertSame(
            ['https://cdn.test/480.avif 480w', null, 'image/jpeg'],
            [$sources->srcset('avif'), $sources->srcset('webp'), $sources->fallback->mimeType],
        );
    }

    #[Test]
    public function refusesAnImageWithNoFilesInTheFallbackFormat(): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('“card” has no jpg files for this image.');

        self::build([VariantFileFactory::make(480, Format::Avif)]);
    }

    #[Test]
    public function skipsFormatsThatHaveNoFiles(): void
    {
        $sources = self::build([VariantFileFactory::make(
            480,
        )], SizeSetFactory::make(formats: [Format::Webp, Format::Jpg]));

        static::assertSame(
            [Format::Jpg],
            array_map(static fn(FormatSource $source): Format => $source->format, $sources->sources),
        );
    }
}
