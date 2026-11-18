<?php

declare(strict_types=1);

namespace parallelogram\images\test\Integration\craft;

use craft\base\FsInterface;
use craft\elements\Asset;
use DateTime;
use Imagick;
use parallelogram\images\backends\BackendAvailability;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\VolumeRoutingGenerator;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\craft\AssetSource;
use parallelogram\images\craft\CraftSourceFile;
use parallelogram\images\craft\CraftSourceLookup;
use parallelogram\images\craft\DeliveryFactory;
use parallelogram\images\craft\FsVariantStore;
use parallelogram\images\craft\MutexVariantLock;
use parallelogram\images\delivery\Delivery;
use parallelogram\images\delivery\DeliveryOutcome;
use parallelogram\images\delivery\DeliveryPorts;
use parallelogram\images\delivery\VariantRequestHandler;
use parallelogram\images\models\Settings;
use parallelogram\images\processing\gd\GdGenerator;
use parallelogram\images\processing\ImagickGenerator;
use parallelogram\images\processing\Rect;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\sizing\UnknownSizeSetException;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\test\TestAsset\Fake\FakeCraftEnvironment;
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
use yii\mutex\Mutex;

use function array_key_exists;

/**
 * A real variant request through everything the factory wires together, with
 * an in-memory filesystem, a stubbed asset and the real ImageMagick generator.
 *
 * @internal
 */
#[CoversClass(DeliveryFactory::class)]
#[UsesClass(AssetSource::class)]
#[UsesClass(BackendAvailability::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendConfig::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(Box::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(CraftSourceFile::class)]
#[UsesClass(CraftSourceLookup::class)]
#[UsesClass(Delivery::class)]
#[UsesClass(DeliveryPorts::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(EncodingConfig::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FitConfig::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(FsVariantStore::class)]
#[UsesClass(GdGenerator::class)]
#[UsesClass(ImagickGenerator::class)]
#[UsesClass(MutexVariantLock::class)]
#[UsesClass(ParsedVariantPath::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(Rect::class)]
#[UsesClass(ResizePlan::class)]
#[UsesClass(Settings::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetConfig::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[UsesClass(VariantRequestHandler::class)]
#[UsesClass(VolumeRoutingGenerator::class)]
#[Group('integration')]
final class DeliveryFactoryEndToEndTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private array $files = [];

    /**
     * @throws UnknownSizeSetException
     */
    private static function expectedPath(Settings $settings): string
    {
        $path    = new VariantPath('asset/variant');
        $planner = new VariantPlanner(new VariantHasher('secret', new Processing()), $path, $settings->siteQuality());
        $source  = SourceImageFactory::make(
            filename: 'harbour.jpg',
            width: 400,
            height: 300,
            modifiedAt: 1_762_000_000,
        );
        $files = $planner->filesFor($source, $settings->catalogue()->get('card'));

        return ($files[0] ?? static::fail('No variant was planned.'))->path;
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function generatesSavesAndServesARequestedVariant(): void
    {
        $settings = new Settings([
            'variantFilesystem' => 'variants',
            'variantPrefix'     => 'asset/variant',
            'sizeSets'          => ['card' => ['widths' => [200], 'sizes' => '100vw', 'formats' => ['avif']]],
        ]);

        $handler = (new DeliveryFactory(
            new FakeCraftEnvironment($this->filesystem(), $this->mutex(), $this->asset()),
        ))->handler($settings);
        $path = self::expectedPath($settings);

        $delivery = $handler?->handle($path) ?? static::fail('No handler was built.');

        $image = new Imagick();
        $image->pingImageBlob($delivery->body);

        static::assertSame(
            [DeliveryOutcome::Content, 'image/avif', 200, true],
            [$delivery->outcome, $delivery->mimeType, $image->getImageWidth(), array_key_exists($path, $this->files)],
        );
    }

    private function asset(): Asset
    {
        $asset               = $this->createStub(Asset::class);
        $asset->kind         = Asset::KIND_IMAGE;
        $asset->uid          = SourceImageFactory::UID;
        $asset->dateModified = new DateTime('@1762000000');
        $asset->method('getFilename')->willReturn('harbour.jpg');
        $asset->method('getWidth')->willReturn(400);
        $asset->method('getHeight')->willReturn(300);
        $asset->method('getFocalPoint')->willReturn(null);
        $asset->method('getContents')->willReturn(ImageFactory::solid(400, height: 300, colour: 'red', format: 'jpeg'));

        return $asset;
    }

    private function filesystem(): FsInterface
    {
        $fs = $this->createStub(FsInterface::class);
        $fs->method('getRootUrl')->willReturn('/media');
        $fs->method('fileExists')->willReturnCallback(fn(string $path): bool => array_key_exists($path, $this->files));
        $fs->method('read')->willReturnCallback(fn(string $path): string => $this->files[$path] ?? '');
        $fs->method('write')->willReturnCallback(function (string $path, string $contents): void {
            $this->files[$path] = $contents;
        });

        return $fs;
    }

    private function mutex(): Mutex
    {
        $mutex = $this->createStub(Mutex::class);
        $mutex->method('acquire')->willReturn(true);

        return $mutex;
    }
}
