<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\warming;

use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\MakeResult;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantMaker;
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
use parallelogram\images\test\TestAsset\Fake\InMemoryVariantStore;
use parallelogram\images\test\TestAsset\Fake\RecordingGenerator;
use parallelogram\images\test\TestAsset\Fake\ScriptedLock;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use parallelogram\images\warming\VariantWarmer;
use parallelogram\images\warming\WarmResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * @internal
 */
#[CoversClass(VariantWarmer::class)]
#[CoversClass(WarmResult::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(GenerationFailedException::class)]
#[UsesClass(MakeResult::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(StorageFailedException::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantMaker::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[Group('unit')]
final class VariantWarmerTest extends TestCase
{
    /**
     * Two size sets, "card" and "tile", each two AVIF and two JPG files wide.
     */
    private static function warmer(InMemoryVariantStore $store, bool $fails = false): VariantWarmer
    {
        $path    = new VariantPath('asset/variant');
        $planner = new VariantPlanner(new VariantHasher('secret', new Processing()), $path, new QualityTable());

        return new VariantWarmer(
            $planner,
            new SizeSetCatalogue([SizeSetFactory::make('card'), SizeSetFactory::make('tile', widths: [200, 400])]),
            new VariantMaker(new RecordingGenerator($fails), $store, new ScriptedLock()),
            $store,
        );
    }

    #[Test]
    public function addsResultsTogether(): void
    {
        static::assertEquals(
            new WarmResult(
                made: 4,
                present: 6,
                failed: 8,
            ),
            (new WarmResult(1, 2, 3))->plus(new WarmResult(3, 4, 5)),
        );
    }

    #[Test]
    public function countsFilesThatCantBeMadeAndCarriesOn(): void
    {
        $result = self::warmer(new InMemoryVariantStore(), fails: true)->warm(SourceImageFactory::make(), ['card']);

        static::assertEquals(new WarmResult(failed: 4), $result);
    }

    #[Test]
    public function countsFilesThatCantBeStored(): void
    {
        $result = self::warmer(new InMemoryVariantStore(broken: true))->warm(SourceImageFactory::make(), ['card']);

        static::assertEquals(new WarmResult(failed: 4), $result);
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function ignoresSizeSetsThatNoLongerExist(): void
    {
        $warmer = self::warmer(new InMemoryVariantStore());

        static::assertEquals(new WarmResult(), $warmer->warm(SourceImageFactory::make(), ['gone']));
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function listsTheFilesOfTheRequestedSizeSetsThatAreMissing(): void
    {
        $store  = new InMemoryVariantStore();
        $warmer = self::warmer($store);
        $warmer->warm(SourceImageFactory::make(), ['card']);

        static::assertSame(
            [0, 4],
            [
                count($warmer->missing(SourceImageFactory::make(), ['card'])),
                count($warmer->missing(SourceImageFactory::make(), ['card', 'tile'])),
            ],
        );
    }

    #[Test]
    public function makesMissingFilesAndLeavesTheRest(): void
    {
        $store  = new InMemoryVariantStore();
        $warmer = self::warmer($store);

        static::assertEquals(
            [
                new WarmResult(made: 4),
                new WarmResult(
                    made: 4,
                    present: 4,
                ),
                8,
            ],
            [
                $warmer->warm(SourceImageFactory::make(), ['card']),
                $warmer->warm(SourceImageFactory::make(), ['card', 'tile']),
                count($store->files),
            ],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function namesMissingFilesByTheirPathOnTheVariantFilesystem(): void
    {
        $missing = self::warmer(new InMemoryVariantStore())->missing(SourceImageFactory::make(), ['card']);

        static::assertStringStartsWith(
            'asset/variant/' . SourceImageFactory::UID . '/',
            $missing[0]->path ?? 'no files were missing',
        );
    }
}
