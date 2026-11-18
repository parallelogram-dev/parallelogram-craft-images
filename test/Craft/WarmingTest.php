<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use craft\base\BaseFsInterface;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\fields\Assets;
use craft\models\Volume;
use Override;
use parallelogram\images\craft\FieldVolumes;
use parallelogram\images\craft\SizeSetScope;
use parallelogram\images\craft\Warming;
use parallelogram\images\craft\WarmingEvents;
use parallelogram\images\jobs\DeleteVariantsJob;
use parallelogram\images\jobs\GenerateVariantsJob;
use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\Trait\CountsQueuedJobsTrait;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\Exception;

use function array_map;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Making and removing variants ahead of time, against the sandbox site: its
 * test volume, the "gallery" Assets field on the Pages section, and the
 * "card" size set that {@see configureVariants()} sets up.
 *
 * @internal
 */
#[CoversClass(DeleteVariantsJob::class)]
#[CoversClass(FieldVolumes::class)]
#[CoversClass(GenerateVariantsJob::class)]
#[CoversClass(SizeSetScope::class)]
#[CoversClass(Warming::class)]
#[CoversClass(WarmingEvents::class)]
#[Group('integration')]
#[Group('craft')]
final class WarmingTest extends TestCase
{
    use CountsQueuedJobsTrait;
    use UsesCraftAppTrait;

    private const string GALLERY_FIELD = '7abbc7e1-ad75-4a6c-b39d-ea23ffee2666';

    private const string ORPHAN_FOLDER = '3f1a7c52-9d44-4b0e-8a61-2c5e7d9b1f08';

    private const string GENERATING = 'Generating image variants';

    private const string REMOVING = 'Removing image variants';

    /**
     * @throws Throwable
     */
    #[Test]
    public function addsNoRemovalJobForAnAssetWithoutAUid(): void
    {
        $before     = self::queuedJobs(self::REMOVING);
        $asset      = new Asset();
        $asset->uid = null;

        WarmingEvents::queueForDeletion(new Event(['sender' => $asset]));
        WarmingEvents::queueForDeletion(new Event(['sender' => $this]));

        static::assertSame($before, self::queuedJobs(self::REMOVING));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function assignsNothingForAnAssetWithoutAVolume(): void
    {
        $scope = new SizeSetScope(new FieldAssignments([new FieldAssignment(self::GALLERY_FIELD, ['card'])]));

        static::assertSame([], $scope->forVolume(new Asset()));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function attachesEachHandlerOnceHoweverOftenItIsRegistered(): void
    {
        $this->assign();
        WarmingEvents::register();
        WarmingEvents::register();
        $before = self::queuedJobs(self::GENERATING);

        $this->redImage();

        static::assertSame($before + 1, self::queuedJobs(self::GENERATING));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function doesNothingForAssetsThatAreGoneOrWhileVariantsAreOff(): void
    {
        $this->assign();
        $asset = $this->redImage();
        (new GenerateVariantsJob(['assetId' => 999_999_999]))->execute(Craft::$app->getQueue());
        $this->plugin()->setSettings(['variantFilesystem' => '']);
        (new GenerateVariantsJob(['assetId' => $asset->id]))->execute(Craft::$app->getQueue());

        static::assertNull($this->plugin()->warming());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function ignoresPagesWhileVariantsAreOffAndDraftsAndCopiesForOtherSites(): void
    {
        $this->assign();
        $entry  = $this->pageWith([$this->redImage()]);
        $before = self::queuedJobs(self::GENERATING);

        $draft             = clone $entry;
        $draft->draftId    = 1;
        $copy              = clone $entry;
        $copy->propagating = true;
        WarmingEvents::queueForElement(new ElementEvent(['element' => $draft, 'isNew' => true]));
        WarmingEvents::queueForElement(new ElementEvent(['element' => $copy, 'isNew' => true]));
        $this->plugin()->setSettings(['variantFilesystem' => '']);
        WarmingEvents::queueForElement(new ElementEvent(['element' => $entry, 'isNew' => true]));

        static::assertSame($before, self::queuedJobs(self::GENERATING));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function knowsWhichVolumesAFieldAccepts(): void
    {
        $volume  = $this->volume();
        $folder  = Craft::$app->getAssets()->getRootFolderByVolumeId((int) $volume->id);
        $loose   = Craft::$app->getAssets()->getUserTemporaryUploadFolder();
        $uid     = (string) $volume->uid;
        $accepts = static fn(mixed $sources): bool => FieldVolumes::accept(new Assets(['sources' => $sources]), $uid);

        static::assertSame(
            [true, true, false, true, false, false, false],
            [
                $accepts('*'),
                $accepts(["volume:{$uid}"]),
                $accepts(['volume:somewhere-else']),
                $accepts(["folder:{$folder?->uid}"]),
                $accepts(['folder:no-such-folder']),
                $accepts(["folder:{$loose->uid}"]),
                $accepts(['section:pages']),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function leavesAFolderWhoseVolumeIsGoneUnaccepted(): void
    {
        $db = Craft::$app->getDb();
        $db->createCommand('SET FOREIGN_KEY_CHECKS = 0')->execute();

        try {
            $db->createCommand()
                ->insert(Table::VOLUMEFOLDERS, [
                    'volumeId' => 999_999,
                    'name'     => 'orphan',
                    'path'     => 'orphan/',
                    'uid'      => self::ORPHAN_FOLDER,
                ])
                ->execute();
        } finally {
            $db->createCommand('SET FOREIGN_KEY_CHECKS = 1')->execute();
        }

        static::assertFalse(FieldVolumes::accept(
            new Assets(['sources' => ['folder:' . self::ORPHAN_FOLDER]]),
            (string) $this->volume()->uid,
        ));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function leavesFilesThatArentResizedAloneWhenRebuilding(): void
    {
        $this->assign();
        $svg = $this->makeImageAsset(
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>',
            filename: 'logo.svg',
        );

        static::assertSame([null, []], [$this->warming()->rebuild($svg), $this->warming()->missing($svg)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function leavesImagesAloneWhenTheirImageServiceCantBeUsed(): void
    {
        $this->assign();
        $asset = $this->redImage();
        $this->assign([
            'backends' => [
                'volumes'      => [(string) $asset->getVolume()->uid => 'imgproxy'],
                'imgproxyUrl'  => 'https://img.test',
                'imgproxyKey'  => 'not hex',
                'imgproxySalt' => 'not hex',
            ],
        ]);

        static::assertSame([[], null], [$this->warming()->missing($asset), $this->warming()->rebuild($asset)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function leavesImagesOnVolumesThatUseAnImageServiceAlone(): void
    {
        $this->assign();
        $asset = $this->redImage();
        $this->assign([
            'backends' => [
                'volumes'           => [(string) $asset->getVolume()->uid => 'cloudflare'],
                'cloudflareZoneUrl' => 'https://cdn.test',
            ],
        ]);

        static::assertSame([[], null], [$this->warming()->missing($asset), $this->warming()->rebuild($asset)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function leavesTheOldVariantsWhenTheNewOnesCantBeMade(): void
    {
        $this->assign();
        $asset = $this->redImage();
        $asset->getVolume()->write($asset->getPath(), contents: 'this is not an image');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("couldn't be made");

        $this->runGeneration($asset);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function makesNothingForFilesThatArentResized(): void
    {
        $this->assign();
        $svg = $this->makeImageAsset(
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>',
            filename: 'logo.svg',
        );

        (new GenerateVariantsJob(['assetId' => $svg->id]))->execute(Craft::$app->getQueue());
        (new GenerateVariantsJob(['assetId' => $svg->id, 'clean' => true]))->execute(Craft::$app->getQueue());

        static::assertNull($this->warming()->warm($svg, ['card']));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function makesTheSizeSetsAssignedToFieldsThatAcceptTheVolume(): void
    {
        $this->assign();
        $asset = $this->redImage();

        $this->runGeneration($asset);

        static::assertSame([[], ['card']], [
            $this->warming()->missing($asset),
            $this->warming()->scope->forVolume($asset),
        ]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function onlyAssignsSizeSetsThroughAssetsFieldsThatAcceptTheVolume(): void
    {
        $asset       = $this->redImage();
        $placeholder = (string) Craft::$app->getFields()->getFieldByHandle('placeholder')?->uid;

        static::assertSame(
            [['card'], [], []],
            [
                $this->sizeSetsFor($asset, self::GALLERY_FIELD),
                $this->sizeSetsFor($asset, $placeholder),
                $this->sizeSetsFor($asset, fieldUid: 'no-such-field'),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function queuesVariantsForEachNewImageButNotForFilesThatArentResized(): void
    {
        $this->assign();
        $before = self::queuedJobs(self::GENERATING);
        $this->redImage();
        $this->makeImageAsset('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>', filename: 'logo.svg');

        static::assertSame($before + 1, self::queuedJobs(self::GENERATING));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function queuesVariantsForImagesPlacedInAnAssignedField(): void
    {
        $this->assign();
        $asset  = $this->redImage();
        $before = self::queuedJobs(self::GENERATING);

        $entry = $this->pageWith([$asset]);
        $saved = self::queuedJobs(self::GENERATING);
        Craft::$app->getElements()->saveElement($entry);
        $resaved = self::queuedJobs(self::GENERATING);
        $entry->setFieldValue('gallery', [$this->redImage()->id]);
        Craft::$app->getElements()->saveElement($entry);

        static::assertSame([$before + 1, $before + 1, $before + 2], [
            $saved,
            $resaved,
            self::queuedJobs(self::GENERATING) - 1,
        ]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function queuesVariantsWhenAnImageIsReplacedButNotWhenSavedAgain(): void
    {
        $this->assign();
        $asset  = $this->redImage();
        $before = self::queuedJobs(self::GENERATING);
        Craft::$app->getElements()->saveElement($asset);
        $resaved = self::queuedJobs(self::GENERATING);

        $path = (string) tempnam(sys_get_temp_dir(), prefix: 'p-images-');
        file_put_contents($path, ImageFactory::solid(40, height: 20, colour: 'blue', format: 'jpeg'));
        Craft::$app->getAssets()->replaceAssetFile($asset, $path, $asset->getFilename());

        static::assertSame([$before, $before + 1], [$resaved, self::queuedJobs(self::GENERATING)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function rebuildsAnImageFromScratch(): void
    {
        $this->assign();
        $asset = $this->redImage();
        $this->runGeneration($asset);
        $fs    = $this->variantFs();
        $stale = "asset/variant/{$asset->uid}/0123456789abcdef/harbour.avif";
        $fs->write($stale, contents: 'old');

        $result = $this->warming()->rebuild($asset);

        static::assertSame([1, false, []], [
            $result?->made,
            $fs->fileExists($stale),
            $this->warming()->missing($asset),
        ]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function removesAllVariantsOfADeletedAsset(): void
    {
        $this->assign();
        $asset = $this->redImage();
        $this->runGeneration($asset);
        $fs     = $this->variantFs();
        $before = self::queuedJobs(self::REMOVING);
        $folder = "asset/variant/{$asset->uid}";

        Craft::$app->getElements()->deleteElement($asset, hardDelete: true);
        $queued = self::queuedJobs(self::REMOVING);
        (new DeleteVariantsJob(['assetUid' => $asset->uid]))->execute(Craft::$app->getQueue());

        static::assertSame([$before + 1, false], [$queued, $fs->directoryExists($folder)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function removesTheVariantsOfAReplacedFileOnceTheNewOnesExist(): void
    {
        $this->assign();
        $asset = $this->redImage();
        $this->runGeneration($asset);
        $fs    = $this->variantFs();
        $stale = "asset/variant/{$asset->uid}/0123456789abcdef/harbour.avif";
        $fs->write($stale, contents: 'old');

        (new GenerateVariantsJob(['assetId' => $asset->id, 'clean' => true]))->execute(Craft::$app->getQueue());

        static::assertSame([false, []], [$fs->fileExists($stale), $this->warming()->missing($asset)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function sweepsOrphanedFoldersLeftBehind(): void
    {
        $this->assign();
        $asset = $this->redImage();
        $this->runGeneration($asset);
        $this->variantFs()->write(
            'asset/variant/1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f/0123456789abcdef/gone.avif',
            contents: 'orphan',
        );

        $result = $this->warming()->sweep();

        static::assertSame([1, 0, 1], [$result->assetFolders, $result->staleFolders, $result->checkedAssets]);
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
     * Pre-generates "card" for everything placed in the gallery field.
     *
     * @param array<string, mixed> $overrides
     *
     * @throws Exception
     */
    private function assign(array $overrides = []): void
    {
        $this->configureVariants(
            $overrides
                + [
                    'fieldAssignments' => [[
                        'fieldUid'  => self::GALLERY_FIELD,
                        'layoutUid' => null,
                        'sizeSets'  => ['card'],
                    ]],
                ],
        );
    }

    /**
     * @throws Throwable
     */
    /**
     * @param list<Asset> $assets
     *
     * @throws Throwable
     */
    private function pageWith(array $assets): Entry
    {
        $section = Craft::$app->getEntries()->getSectionByHandle('pages');
        $type    = Craft::$app->getEntries()->getEntryTypeByHandle('page');
        $entry   = new Entry(['sectionId' => $section?->id, 'typeId' => $type?->id, 'title' => 'A page']);
        $entry->setFieldValue('gallery', array_map(static fn(Asset $asset): ?int => $asset->id, $assets));

        if (! Craft::$app->getElements()->saveElement($entry)) {
            static::fail('The test page could not be saved.');
        }

        return $entry;
    }

    /**
     * @throws Throwable
     */
    private function redImage(): Asset
    {
        return $this->makeImageAsset(ImageFactory::solid(40, height: 20, colour: 'red', format: 'jpeg'));
    }

    /**
     * @throws Throwable
     */
    private function runGeneration(Asset $asset): void
    {
        (new GenerateVariantsJob(['assetId' => $asset->id]))->execute(Craft::$app->getQueue());
    }

    /**
     * @return list<string>
     */
    private function sizeSetsFor(Asset $asset, string $fieldUid): array
    {
        $scope = new SizeSetScope(new FieldAssignments([new FieldAssignment($fieldUid, ['card'])]));

        return $scope->forVolume($asset);
    }

    private function variantFs(): BaseFsInterface
    {
        return (
            Craft::$app->getFs()->getFilesystemByHandle('testVariants') ?? static::fail(
                'The sandbox has no testVariants filesystem.',
            )
        );
    }

    private function volume(): Volume
    {
        return (
            Craft::$app->getVolumes()->getVolumeByHandle('testImages') ?? static::fail(
                'The sandbox has no testImages volume.',
            )
        );
    }

    /**
     * @throws Exception
     */
    private function warming(): Warming
    {
        return $this->plugin()->warming() ?? static::fail('Variants are not configured.');
    }
}
