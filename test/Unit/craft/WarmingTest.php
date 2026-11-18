<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\elements\Asset;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\craft\AssetFiles;
use parallelogram\images\craft\AssetSource;
use parallelogram\images\craft\SizeSetScope;
use parallelogram\images\craft\VariantBackends;
use parallelogram\images\craft\Warming;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\MakeResult;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantMaker;
use parallelogram\images\remote\CloudflareUrls;
use parallelogram\images\remote\ImgproxySigner;
use parallelogram\images\remote\ImgproxyUrls;
use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\FieldAssignments;
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
use parallelogram\images\test\TestAsset\Fake\StaticSourceLookup;
use parallelogram\images\test\Trait\StubsAssetsTrait;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use parallelogram\images\warming\SweepResult;
use parallelogram\images\warming\VariantCleaner;
use parallelogram\images\warming\VariantWarmer;
use parallelogram\images\warming\WarmResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function array_keys;
use function count;

/**
 * @internal
 */
#[CoversClass(Warming::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(AssetFiles::class)]
#[UsesClass(AssetSource::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(Box::class)]
#[UsesClass(CloudflareUrls::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(FieldAssignments::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(GenerationFailedException::class)]
#[UsesClass(ImgproxySigner::class)]
#[UsesClass(ImgproxyUrls::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(MakeResult::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(RemoteSettings::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetScope::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(StorageFailedException::class)]
#[UsesClass(SweepResult::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantBackends::class)]
#[UsesClass(VariantCleaner::class)]
#[UsesClass(VariantFile::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantMaker::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[UsesClass(VariantWarmer::class)]
#[UsesClass(WarmResult::class)]
#[Group('unit')]
final class WarmingTest extends TestCase
{
    use StubsAssetsTrait;

    private const string STALE = 'asset/variant/' . SourceImageFactory::UID . '/0123456789abcdef/harbour.jpg';

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function forgetsEveryVariantOfADeletedAsset(): void
    {
        $store                     = new InMemoryVariantStore();
        $store->files[self::STALE] = 'old';

        $this->warming($store)->forget(SourceImageFactory::UID);

        static::assertSame([], array_keys($store->files));
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function keepsTheOldVariantsWhenNewOnesCouldntBeMade(): void
    {
        $store                     = new InMemoryVariantStore();
        $store->files[self::STALE] = 'old';

        $result = $this->warming($store, fails: true)->warmAndClean($this->stubAsset(), ['card']);

        static::assertEquals([new WarmResult(failed: 4), 'old'], [
            $result,
            $store->files[self::STALE] ?? 'removed',
        ]);
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function leavesFilesThatAreNotResizedAlone(): void
    {
        $warming = $this->warming(new InMemoryVariantStore());
        $svg     = $this->stubAsset(['filename' => 'logo.svg']);

        static::assertSame([null, null], [$warming->warm($svg, ['card']), $warming->warmAndClean($svg, ['card'])]);
    }

    #[Test]
    public function leavesVolumesThatUseAnImageServiceToThatService(): void
    {
        $store   = new InMemoryVariantStore();
        $warming = $this->warming($store, remote: new BackendChoice(BackendKind::Auto, [
            'volume-1' => BackendKind::Cloudflare,
        ]));

        static::assertSame([null, 0], [$warming->warm($this->stubAsset(), ['card']), count($store->files)]);
    }

    #[Test]
    public function leavesVolumesWhoseImageServiceCantBeUsed(): void
    {
        $warming = $this->warming(
            new InMemoryVariantStore(),
            remote: new BackendChoice(BackendKind::Auto, [
                'volume-1' => BackendKind::Imgproxy,
            ]),
        );

        static::assertNull($warming->warm($this->stubAsset(), ['card']));
    }

    #[Test]
    public function makesTheRequestedVariants(): void
    {
        $store = new InMemoryVariantStore();

        static::assertEquals(
            [new WarmResult(made: 4), 4],
            [$this->warming($store)->warm($this->stubAsset(), ['card']), count($store->files)],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function removesVariantsNoSizeSetDefinesOnceTheNewOnesExist(): void
    {
        $store                     = new InMemoryVariantStore();
        $store->files[self::STALE] = 'old';

        $result = $this->warming($store)->warmAndClean($this->stubAsset(), ['card']);

        static::assertEquals([new WarmResult(made: 4), false, 4], [
            $result,
            array_key_exists(self::STALE, $store->files),
            count($store->files),
        ]);
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function sweepsStaleVariantsAndDeletedAssets(): void
    {
        $store   = new InMemoryVariantStore();
        $warming = $this->warming($store);
        $warming->warm($this->stubAsset(), ['card']);
        $store->files[self::STALE]                                                                    = 'old';
        $store->files['asset/variant/1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f/0123456789abcdef/gone.jpg'] = 'orphan';

        static::assertEquals(
            new SweepResult(
                assetFolders: 1,
                staleFolders: 1,
                checkedAssets: 1,
            ),
            $warming->sweep(),
        );
    }

    #[Test]
    public function treatsAnAssetWithoutAVolumeAsStoredHere(): void
    {
        static::assertInstanceOf(
            WarmResult::class,
            $this->warming(new InMemoryVariantStore())->warm($this->stubAsset(['volumeUid' => null]), ['card']),
        );
    }

    /**
     * @return list<SourceImage>
     */
    private function sourcesOf(Asset $asset): array
    {
        $source = AssetSource::from($asset);

        return null === $source ? [] : [$source];
    }

    private function warming(
        InMemoryVariantStore $store,
        bool $fails = false,
        BackendChoice $remote = new BackendChoice(),
    ): Warming {
        $path      = new VariantPath('asset/variant');
        $planner   = new VariantPlanner(new VariantHasher('secret', new Processing()), $path, new QualityTable());
        $catalogue = new SizeSetCatalogue([SizeSetFactory::make('card')]);

        return new Warming(
            new SizeSetScope(new FieldAssignments()),
            new VariantWarmer(
                $planner,
                $catalogue,
                new VariantMaker(new RecordingGenerator($fails), $store, new ScriptedLock()),
                $store,
            ),
            new VariantCleaner($path, $planner, $catalogue, $store),
            new VariantBackends($remote, new Processing(), new RemoteSettings(cloudflareZoneUrl: 'https://cdn.test')),
            new StaticSourceLookup($this->sourcesOf($this->stubAsset())),
        );
    }
}
