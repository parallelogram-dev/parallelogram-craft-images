<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\errors\InvalidFieldException;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\web\View;
use Override;
use parallelogram\images\colour\GdSampler;
use parallelogram\images\craft\PlaceholderBackfill;
use parallelogram\images\craft\PlaceholderEvents;
use parallelogram\images\craft\PlaceholderMaker;
use parallelogram\images\craft\PlaceholderStore;
use parallelogram\images\fields\PlaceholderColourField;
use parallelogram\images\jobs\PlaceholderJob;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\TestAsset\Fake\FailingSampler;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;

use function count;
use function file_put_contents;
use function in_array;
use function str_contains;
use function sys_get_temp_dir;
use function tempnam;

/**
 * Placeholder colours on the sandbox's test volume, whose layout has a
 * Placeholder Colour field with the handle `placeholder`.
 *
 * @internal
 */
#[CoversClass(PlaceholderBackfill::class)]
#[CoversClass(PlaceholderColourField::class)]
#[CoversClass(PlaceholderEvents::class)]
#[CoversClass(PlaceholderJob::class)]
#[CoversClass(PlaceholderMaker::class)]
#[CoversClass(PlaceholderStore::class)]
#[Group('integration')]
#[Group('craft')]
final class PlaceholderTest extends TestCase
{
    use UsesCraftAppTrait;

    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>';

    /**
     * The asset as stored now.
     *
     * @throws Throwable
     */
    private static function fresh(Asset $asset): Asset
    {
        return (
            Craft::$app->getAssets()->getAssetById((int) $asset->id)
                ?? static::fail("Asset {$asset->id} is gone.")
        );
    }

    private static function queued(): int
    {
        return (int) (new Query())->from(Table::QUEUE)
            ->where(['description' => 'Working out an image’s placeholder colour'])
            ->count();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function describesItselfAndShowsTheColourReadOnly(): void
    {
        $field = new PlaceholderColourField();
        $asset = new Asset();

        static::assertSame(
            ['Placeholder Colour', 'palette', 'string|null', 'char(7)', true, true],
            [
                PlaceholderColourField::displayName(),
                PlaceholderColourField::icon(),
                PlaceholderColourField::phpType(),
                PlaceholderColourField::dbType(),
                str_contains($field->getStaticHtml('#7A8B6C', $asset), 'background-color: #7a8b6c'),
                str_contains($field->getInputHtml(null, $asset), 'Worked out when the image is uploaded.'),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function fillsInEveryColourWhenForced(): void
    {
        $done = $this->redImage();
        (new PlaceholderJob(['assetId' => $done->id]))->execute(Craft::$app->getQueue());

        $backfill = new PlaceholderBackfill(new PlaceholderMaker(new GdSampler()), force: true);
        $backfill->run();

        static::assertSame('Placeholder colours: 1 worked out, 0 skipped, 0 failed.', $backfill->summary());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function fillsInMissingColoursAndSkipsTheRest(): void
    {
        $done = $this->redImage();
        (new PlaceholderJob(['assetId' => $done->id]))->execute(Craft::$app->getQueue());
        $this->redImage();
        $this->makeImageAsset(self::SVG, filename: 'logo.svg');

        $backfill = new PlaceholderBackfill(new PlaceholderMaker(new GdSampler()));

        static::assertSame(
            [true, 'Placeholder colours: 1 worked out, 2 skipped, 0 failed.'],
            [$backfill->run(), $backfill->summary()],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function ignoresJobsForAssetsThatAreGone(): void
    {
        $before = self::queued();

        (new PlaceholderJob(['assetId' => 999_999_999]))->execute(Craft::$app->getQueue());

        static::assertSame($before, self::queued());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function makesNoColourForAnImageWithNoOpaquePixels(): void
    {
        $asset = $this->makeImageAsset(ImageFactory::transparentPng(40, height: 20), filename: 'clear.png');

        static::assertNull((new PlaceholderMaker(new GdSampler()))->make($asset));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function queuesAColourForEachNewImage(): void
    {
        $before = self::queued();
        $this->redImage();
        $this->makeImageAsset(self::SVG, filename: 'logo.svg');

        static::assertSame($before + 1, self::queued());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function queuesAColourWhenAnImageIsReplacedButNotWhenSavedAgain(): void
    {
        $asset  = $this->redImage();
        $before = self::queued();
        Craft::$app->getElements()->saveElement($asset);
        $resaved = self::queued();

        $path = (string) tempnam(sys_get_temp_dir(), prefix: 'p-images-');
        file_put_contents($path, ImageFactory::solid(40, height: 20, colour: 'blue', format: 'jpeg'));
        Craft::$app->getAssets()->replaceAssetFile($asset, $path, $asset->getFilename());

        static::assertSame([$before, $before + 1], [$resaved, self::queued()]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function readsNoColourWithoutAUsableField(): void
    {
        $unnamed = new PlaceholderColourField();
        $named   = new PlaceholderColourField(['handle' => 'placeholder']);

        static::assertSame(
            [null, null, null],
            [
                PlaceholderStore::colourOf(new Asset()),
                PlaceholderStore::colourOf($this->assetWithLayoutField($unnamed)),
                PlaceholderStore::colourOf($this->assetWithLayoutField($named)),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function registersTheFieldTypeAndQueuesOncePerUploadHoweverOftenItIsRegistered(): void
    {
        PlaceholderEvents::register();
        PlaceholderEvents::register();
        $before = self::queued();
        $this->redImage();

        static::assertSame(
            [true, $before + 1],
            [
                in_array(PlaceholderColourField::class, Craft::$app->getFields()->getAllFieldTypes(), strict: true),
                self::queued(),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function reportsImagesThatAreDamaged(): void
    {
        $this->redImage();

        $backfill = new PlaceholderBackfill(new PlaceholderMaker(new FailingSampler()));

        static::assertSame(
            [false, 'Placeholder colours: 0 worked out, 0 skipped, 1 failed.', true],
            [
                $backfill->run(),
                $backfill->summary(),
                str_contains($backfill->failures()[0] ?? '', 'The image is damaged.'),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function reportsImagesWhoseFilesCantBeRead(): void
    {
        $asset = $this->redImage();
        $asset->getVolume()->getFs()->deleteFile($asset->getPath());

        $backfill = new PlaceholderBackfill(new PlaceholderMaker(new GdSampler()));

        static::assertSame(
            [false, 1, 'Placeholder colours: 0 worked out, 0 skipped, 1 failed.'],
            [$backfill->run(), count($backfill->failures()), $backfill->summary()],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function showsTheColourBehindThePicture(): void
    {
        $this->configureVariants();
        $asset = $this->redImage();
        (new PlaceholderJob(['assetId' => $asset->id]))->execute(Craft::$app->getQueue());

        /** @var View $view Craft's view is always its web view. */
        $view = Craft::$app->getView();

        static::assertStringContainsString(
            'style="background-color:#fe0000"',
            $view->renderString("{{ asset.picture('card') }}", ['asset' => self::fresh($asset)]),
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function storesTheColourWithoutSavingTheAsset(): void
    {
        $asset = $this->redImage();

        (new PlaceholderJob(['assetId' => $asset->id]))->execute(Craft::$app->getQueue());

        $fresh = self::fresh($asset);

        static::assertSame(
            ['#fe0000', $asset->dateUpdated?->getTimestamp()],
            [PlaceholderStore::colourOf($fresh), $fresh->dateUpdated?->getTimestamp()],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function writesNothingWithoutAFieldInstanceOrSavedAsset(): void
    {
        $volume               = Craft::$app->getVolumes()->getVolumeByHandle('testImages');
        $unsaved              = new Asset(['volumeId' => $volume?->id]);
        $noLayout             = new PlaceholderColourField();
        $noUid                = new PlaceholderColourField();
        $noUid->layoutElement = new CustomField();

        static::assertSame(
            [false, false, false, false],
            [
                PlaceholderStore::write(new Asset(), colour: '#000000'),
                PlaceholderStore::write($this->assetWithLayoutField($noLayout), colour: '#000000'),
                PlaceholderStore::write($this->assetWithLayoutField($noUid), colour: '#000000'),
                PlaceholderStore::write($unsaved, colour: '#000000'),
            ],
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

    private function assetWithLayoutField(PlaceholderColourField $field): Asset
    {
        $layout = $this->createStub(FieldLayout::class);
        $layout->method('getCustomFields')->willReturn([$field]);

        $asset     = $this->createStub(Asset::class);
        $asset->id = 1;
        $asset->method('getFieldLayout')->willReturn($layout);
        $asset->method('getFieldValue')->willThrowException(new InvalidFieldException('placeholder'));

        return $asset;
    }

    /**
     * @throws Throwable
     */
    private function redImage(): Asset
    {
        return $this->makeImageAsset(ImageFactory::solid(40, height: 20, colour: 'red', format: 'jpeg'));
    }
}
