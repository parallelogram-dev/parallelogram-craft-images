<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use craft\elements\Asset;
use Override;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\craft\AssetSource;
use parallelogram\images\craft\BatchResult;
use parallelogram\images\craft\BatchWarming;
use parallelogram\images\craft\Operation;
use parallelogram\images\craft\SizeSetScope;
use parallelogram\images\craft\VariantBackends;
use parallelogram\images\craft\Warming;
use parallelogram\images\craft\WarmingCommands;
use parallelogram\images\delivery\VariantMaker;
use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use parallelogram\images\test\TestAsset\Fake\InMemoryVariantStore;
use parallelogram\images\test\TestAsset\Fake\RecordingGenerator;
use parallelogram\images\test\TestAsset\Fake\ScriptedLock;
use parallelogram\images\test\TestAsset\Fake\StaticSourceLookup;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use parallelogram\images\warming\VariantCleaner;
use parallelogram\images\warming\VariantWarmer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;

use function count;

/**
 * Warming, checking and rebuilding many images at once, and the commands
 * built on that, with real assets in the test volume and variants kept in
 * memory so failures can be made to happen.
 *
 * @internal
 */
#[CoversClass(BatchResult::class)]
#[CoversClass(BatchWarming::class)]
#[CoversClass(Operation::class)]
#[CoversClass(Warming::class)]
#[CoversClass(WarmingCommands::class)]
#[Group('integration')]
#[Group('craft')]
final class WarmingToolsTest extends TestCase
{
    use UsesCraftAppTrait;

    private const string GALLERY_FIELD = '7abbc7e1-ad75-4a6c-b39d-ea23ffee2666';

    /**
     * @throws Throwable
     */
    #[Test]
    public function auditsWithoutMakingAnything(): void
    {
        $asset = $this->redImage();
        $store = new InMemoryVariantStore();
        $batch = new BatchWarming($this->warming($store, [$asset]));

        $before = $batch->audit([(int) $asset->id], sizeSet: 'card');
        $batch->warm([(int) $asset->id], sizeSet: 'card');
        $after = $batch->audit([(int) $asset->id], sizeSet: 'card');

        static::assertSame([2, 0, 2], [$before->missing, $after->missing, count($store->files)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function collectsGarbage(): void
    {
        $asset = $this->redImage();
        $store = new InMemoryVariantStore();
        $cmds  = new WarmingCommands($this->warming($store, [$asset]));
        $cmds->warm(
            volume: null,
            sizeSet: 'card',
        );
        $store->files['asset/variant/1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f/0123456789abcdef/gone.jpg'] = 'orphan';

        $outcome = $cmds->gc();

        static::assertSame(
            [0, ['Removed 1 folders of deleted images and 0 of out-of-date variants, checking 1 images.']],
            [$outcome->exit, $outcome->lines],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function countsImagesWhoseStorageCantBeReached(): void
    {
        $asset = $this->redImage();
        $batch = new BatchWarming($this->warming(new InMemoryVariantStore(broken: true), [$asset]));

        static::assertSame(
            [1, 1],
            [
                $batch->audit([(int) $asset->id], sizeSet: null)->files->failed,
                $batch->rebuild([(int) $asset->id])->files->failed,
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function failsForAVolumeThatDoesntExist(): void
    {
        $cmds = new WarmingCommands($this->warming(new InMemoryVariantStore(), []));

        static::assertSame(
            [1, ['There\'s no volume with the handle “nowhere”.']],
            [
                $cmds->warm(
                    volume: 'nowhere',
                    sizeSet: null,
                )->exit,
                $cmds->warm(
                    volume: 'nowhere',
                    sizeSet: null,
                )->lines,
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function failsGarbageCollectionWhenStorageCantBeReached(): void
    {
        $cmds = new WarmingCommands($this->warming(new InMemoryVariantStore(broken: true), []));

        static::assertSame([1, ['Couldn\'t list asset/variant.']], [$cmds->gc()->exit, $cmds->gc()->lines]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function failsWhenSomeVariantsCouldntBeMade(): void
    {
        $asset = $this->redImage();
        $cmds  = new WarmingCommands($this->warming(new InMemoryVariantStore(), [$asset], fails: true));

        static::assertSame(
            [1, ['Warmed 1 images: 0 made, 0 already there, 2 failed, 0 missing, 0 skipped.']],
            [
                $cmds->warm(
                    volume: null,
                    sizeSet: null,
                )->exit,
                $cmds->warm(
                    volume: null,
                    sizeSet: null,
                )->lines,
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function listsImagesInAllVolumesOrOne(): void
    {
        $asset = $this->redImage();
        $svg   = $this->makeImageAsset(
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>',
            filename: 'logo.svg',
        );
        $batch = new BatchWarming($this->warming(new InMemoryVariantStore(), [$asset]));
        $both  = [(int) $asset->id, (int) $svg->id];

        static::assertEqualsCanonicalizing($both, $batch->imageIds(volumeHandle: null));
        static::assertEqualsCanonicalizing($both, $batch->imageIds(volumeHandle: 'testImages'));
        static::assertNull($batch->imageIds(volumeHandle: 'nowhere'));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function rebuildsImagesFromScratch(): void
    {
        $asset = $this->redImage();
        $store = new InMemoryVariantStore();
        $batch = new BatchWarming($this->warming($store, [$asset]));
        $store->files["asset/variant/{$asset->uid}/0123456789abcdef/harbour.jpg"] = 'stale';

        $result = $batch->rebuild([(int) $asset->id]);

        static::assertSame([2, 2], [$result->files->made, count($store->files)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function reportsWhatEachCommandDid(): void
    {
        $asset = $this->redImage();
        $store = new InMemoryVariantStore();
        $cmds  = new WarmingCommands($this->warming($store, [$asset]));

        $audited = $cmds->audit(
            volume: 'testImages',
            sizeSet: 'card',
        );
        $warmed = $cmds->warm(
            volume: 'testImages',
            sizeSet: 'card',
        );
        $clean = $cmds->audit(
            volume: null,
            sizeSet: 'card',
        );
        $rebuilt = $cmds->rebuild(volume: null);

        static::assertSame(
            [
                [1, ['Checked 1 images: 0 made, 0 already there, 0 failed, 2 missing, 0 skipped.']],
                [0, ['Warmed 1 images: 2 made, 0 already there, 0 failed, 0 missing, 0 skipped.']],
                [0, ['Checked 1 images: 0 made, 0 already there, 0 failed, 0 missing, 0 skipped.']],
                [0, ['Rebuilt 1 images: 2 made, 0 already there, 0 failed, 0 missing, 0 skipped.']],
            ],
            [
                [$audited->exit, $audited->lines],
                [$warmed->exit, $warmed->lines],
                [$clean->exit, $clean->lines],
                [$rebuilt->exit, $rebuilt->lines],
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function warmsImagesAndReportsProgress(): void
    {
        $asset = $this->redImage();
        $svg   = $this->makeImageAsset(
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>',
            filename: 'logo.svg',
        );
        $batch    = new BatchWarming($this->warming(new InMemoryVariantStore(), [$asset]));
        $progress = [];

        $result = $batch->warm(
            [(int) $asset->id, (int) $svg->id, 999_999_999],
            sizeSet: 'card',
            progress: static function (int $done, int $total) use (&$progress): void {
                $progress[] = "{$done}/{$total}";
            },
        );

        static::assertSame(
            ['Warmed 3 images: 2 made, 0 already there, 0 failed, 0 missing, 2 skipped.', ['1/3', '2/3', '3/3']],
            [$result->summary(verb: 'Warmed'), $progress],
        );
    }

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        $this->setUpCraft();
    }

    /**
     * @throws ErrorException
     * @throws Exception
     */
    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownCraft();
    }

    /**
     * @throws Throwable
     */
    private function redImage(): Asset
    {
        return $this->makeImageAsset(ImageFactory::solid(40, height: 20, colour: 'red', format: 'jpeg'));
    }

    /**
     * Warming over real assets with variants kept in memory: the "card" size
     * set, assigned to the gallery field.
     *
     * @param list<Asset> $assets Images the sweep should know about.
     */
    private function warming(InMemoryVariantStore $store, array $assets, bool $fails = false): Warming
    {
        $path      = new VariantPath('asset/variant');
        $planner   = new VariantPlanner(new VariantHasher('secret', new Processing()), $path, new QualityTable());
        $catalogue = new SizeSetCatalogue([SizeSetFactory::make('card')]);
        $sources   = [];

        foreach ($assets as $asset) {
            $source = AssetSource::from($asset);

            if (null !== $source) {
                $sources[] = $source;
            }
        }

        return new Warming(
            new SizeSetScope(new FieldAssignments([new FieldAssignment(self::GALLERY_FIELD, ['card'])])),
            new VariantWarmer(
                $planner,
                $catalogue,
                new VariantMaker(new RecordingGenerator($fails), $store, new ScriptedLock()),
                $store,
            ),
            new VariantCleaner($path, $planner, $catalogue, $store),
            new VariantBackends(new BackendChoice(), new Processing(), new RemoteSettings()),
            new StaticSourceLookup($sources),
        );
    }
}
