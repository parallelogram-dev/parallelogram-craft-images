<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\warming;

use parallelogram\images\delivery\StorageFailedException;
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
use parallelogram\images\test\TestAsset\Fake\StaticSourceLookup;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use parallelogram\images\warming\SweepResult;
use parallelogram\images\warming\VariantCleaner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function count;
use function sort;

/**
 * @internal
 */
#[CoversClass(VariantCleaner::class)]
#[CoversClass(SweepResult::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(StorageFailedException::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[Group('unit')]
final class VariantCleanerTest extends TestCase
{
    private const string OTHER_UID = '1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f';

    private static function cleaner(InMemoryVariantStore $store): VariantCleaner
    {
        return self::cleanerFor($store, new SizeSetCatalogue([SizeSetFactory::make('card')]));
    }

    private static function cleanerFor(InMemoryVariantStore $store, SizeSetCatalogue $catalogue): VariantCleaner
    {
        $path = new VariantPath('asset/variant');

        return new VariantCleaner(
            $path,
            new VariantPlanner(new VariantHasher('secret', new Processing()), $path, new QualityTable()),
            $catalogue,
            $store,
        );
    }

    /**
     * Files the "card" size set defines now for the factory image.
     *
     * @return list<string>
     */
    private static function currentPaths(): array
    {
        $path    = new VariantPath('asset/variant');
        $planner = new VariantPlanner(new VariantHasher('secret', new Processing()), $path, new QualityTable());
        $files   = $planner->filesFor(SourceImageFactory::make(), SizeSetFactory::make('card'));

        return array_map(static fn(VariantFile $file): string => $file->path, $files);
    }

    /**
     * @return list<string>
     */
    private static function stored(InMemoryVariantStore $store): array
    {
        $paths = array_keys($store->files);
        sort($paths);

        return $paths;
    }

    /**
     * @param list<string> $paths
     */
    private static function storeWith(array $paths): InMemoryVariantStore
    {
        $store = new InMemoryVariantStore();

        foreach ($paths as $path) {
            $store->files[$path] = 'current';
        }

        return $store;
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function passesOnStorageFailures(): void
    {
        $this->expectException(StorageFailedException::class);

        self::cleaner(new InMemoryVariantStore(broken: true))->removeStale(SourceImageFactory::make());
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function removesEveryVariantOfADeletedAsset(): void
    {
        $store = self::storeWith(self::currentPaths());
        $store->files['asset/variant/' . self::OTHER_UID . '/0123456789abcdef/other.jpg'] = 'keep';

        self::cleaner($store)->removeAll(SourceImageFactory::UID);

        static::assertSame(['asset/variant/' . self::OTHER_UID . '/0123456789abcdef/other.jpg'], self::stored($store));
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function removesNothingWhileNoSizeSetsAreDefined(): void
    {
        $store = self::storeWith(self::currentPaths());

        static::assertSame(
            [0, 4],
            [
                self::cleanerFor($store, new SizeSetCatalogue([]))->removeStale(SourceImageFactory::make()),
                count($store->files),
            ],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function removesTheFoldersOfVariantsNoSizeSetDefinesAnyMore(): void
    {
        $store                = self::storeWith(self::currentPaths());
        $stale                = 'asset/variant/' . SourceImageFactory::UID . '/0123456789abcdef/harbour-dawn.jpg';
        $store->files[$stale] = 'old';

        $removed = self::cleaner($store)->removeStale(SourceImageFactory::make());

        $expected = self::currentPaths();
        sort($expected);

        static::assertSame([1, $expected], [$removed, self::stored($store)]);
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function sweepsNothingWhileNoSizeSetsAreDefined(): void
    {
        $store = self::storeWith(self::currentPaths());

        static::assertEquals(
            [new SweepResult(), 4],
            [
                self::cleanerFor($store, new SizeSetCatalogue([]))->sweep(new StaticSourceLookup([])),
                count($store->files),
            ],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function sweepsOrphanedAssetsAndStaleVariantsAndLeavesOtherFoldersAlone(): void
    {
        $store = self::storeWith(self::currentPaths());
        $store->files['asset/variant/' . SourceImageFactory::UID . '/0123456789abcdef/old.jpg'] = 'stale';
        $store->files['asset/variant/' . self::OTHER_UID . '/0123456789abcdef/gone.jpg']        = 'orphan';
        $store->files['asset/variant/readme/notes.txt']                                         = 'not ours';

        $result = self::cleaner($store)->sweep(new StaticSourceLookup([SourceImageFactory::make()]));

        $expected = [...self::currentPaths(), 'asset/variant/readme/notes.txt'];
        sort($expected);

        static::assertEquals([
            new SweepResult(
                assetFolders: 1,
                staleFolders: 1,
                checkedAssets: 1,
            ),
            $expected,
        ], [
            $result,
            self::stored($store),
        ]);
    }
}
