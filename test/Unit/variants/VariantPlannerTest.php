<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\variants;

use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\ParsedVariantPath;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * @internal
 */
#[CoversClass(VariantPlanner::class)]
#[CoversClass(VariantFile::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(ParsedVariantPath::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantPath::class)]
#[Group('unit')]
final class VariantPlannerTest extends TestCase
{
    /**
     * @param list<VariantFile> $files
     */
    private static function fileAt(array $files, int $index): VariantFile
    {
        return $files[$index] ?? static::fail("Expected a file at index {$index}.");
    }

    private static function planner(): VariantPlanner
    {
        return new VariantPlanner(
            new VariantHasher('secret', new Processing()),
            new VariantPath('asset/variant'),
            new QualityTable(),
        );
    }

    private static function request(string $hash, Format $format): ParsedVariantPath
    {
        return new ParsedVariantPath(SourceImageFactory::UID, $hash, 'harbour-dawn', $format);
    }

    #[Test]
    public function findsTheFileARequestRefersTo(): void
    {
        $planner   = self::planner();
        $source    = SourceImageFactory::make();
        $catalogue = new SizeSetCatalogue([SizeSetFactory::make(widths: [480, 960])]);
        $wanted    = self::fileAt($planner->filesFor($source, SizeSetFactory::make(widths: [480, 960])), index: 1);

        static::assertEquals($wanted, $planner->locate(
            $source,
            self::request($wanted->hash, Format::Avif),
            $catalogue,
        ));
    }

    #[Test]
    public function listsEveryFileForASizeSet(): void
    {
        $files = self::planner()->filesFor(SourceImageFactory::make(), SizeSetFactory::make(widths: [480, 960]));

        static::assertSame(
            ['avif 480', 'avif 960', 'jpg 480', 'jpg 960'],
            array_map(
                static fn(VariantFile $file): string => "{$file->variant->format->value} {$file->variant->box->width}",
                $files,
            ),
        );
    }

    #[Test]
    public function listsSharedFilesOnce(): void
    {
        $catalogue = new SizeSetCatalogue([
            SizeSetFactory::make(
                handle: 'card',
                widths: [480, 960],
            ),
            SizeSetFactory::make(
                handle: 'tile',
                widths: [960, 1280],
            ),
        ]);

        static::assertCount(6, self::planner()->filesForAll(SourceImageFactory::make(), $catalogue));
    }

    #[Test]
    public function refusesARequestForAnotherAsset(): void
    {
        $planner = self::planner();
        $source  = SourceImageFactory::make();
        $file    = self::fileAt($planner->filesFor($source, SizeSetFactory::make()), index: 0);
        $request = new ParsedVariantPath(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            $file->hash,
            'harbour-dawn',
            Format::Avif,
        );

        static::assertNull($planner->locate($source, $request, new SizeSetCatalogue([SizeSetFactory::make()])));
    }

    #[Test]
    public function refusesARequestWithTheWrongFormat(): void
    {
        $planner = self::planner();
        $source  = SourceImageFactory::make();
        $file    = self::fileAt($planner->filesFor($source, SizeSetFactory::make()), index: 0);

        static::assertNull($planner->locate(
            $source,
            self::request($file->hash, Format::Jpg),
            new SizeSetCatalogue([SizeSetFactory::make()]),
        ));
    }

    #[Test]
    public function refusesARequestWithTheWrongName(): void
    {
        $planner = self::planner();
        $source  = SourceImageFactory::make();
        $file    = self::fileAt($planner->filesFor($source, SizeSetFactory::make()), index: 0);
        $request = new ParsedVariantPath(SourceImageFactory::UID, $file->hash, 'renamed', Format::Avif);

        static::assertNull($planner->locate($source, $request, new SizeSetCatalogue([SizeSetFactory::make()])));
    }

    #[Test]
    public function refusesSizesTheSiteDoesNotDefine(): void
    {
        static::assertNull(self::planner()
            ->locate(
                SourceImageFactory::make(),
                self::request('0123456789abcdef', Format::Avif),
                new SizeSetCatalogue([SizeSetFactory::make()]),
            ));
    }
}
