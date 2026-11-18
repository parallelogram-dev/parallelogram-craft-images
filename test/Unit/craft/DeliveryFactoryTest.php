<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\base\FsInterface;
use parallelogram\images\backends\BackendAvailability;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\VolumeRoutingGenerator;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\craft\CraftSourceFile;
use parallelogram\images\craft\CraftSourceLookup;
use parallelogram\images\craft\DeliveryFactory;
use parallelogram\images\craft\FsVariantStore;
use parallelogram\images\craft\MutexVariantLock;
use parallelogram\images\delivery\DeliveryPorts;
use parallelogram\images\delivery\VariantRequestHandler;
use parallelogram\images\models\Settings;
use parallelogram\images\processing\gd\GdGenerator;
use parallelogram\images\processing\ImagickGenerator;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\test\TestAsset\Fake\FakeCraftEnvironment;
use parallelogram\images\test\TestAsset\Fake\SitelessCraftEnvironment;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use yii\mutex\Mutex;

/**
 * @internal
 */
#[CoversClass(DeliveryFactory::class)]
#[UsesClass(BackendAvailability::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendConfig::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(CraftSourceFile::class)]
#[UsesClass(CraftSourceLookup::class)]
#[UsesClass(DeliveryPorts::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(EncodingConfig::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FitConfig::class)]
#[UsesClass(Format::class)]
#[UsesClass(FsVariantStore::class)]
#[UsesClass(GdGenerator::class)]
#[UsesClass(ImagickGenerator::class)]
#[UsesClass(InvalidBackendException::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(MutexVariantLock::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(Settings::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetConfig::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[UsesClass(VariantRequestHandler::class)]
#[UsesClass(VolumeRoutingGenerator::class)]
#[Group('unit')]
final class DeliveryFactoryTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function unusableSettingsProvider(): array
    {
        $configured = ['variantFilesystem' => 'variants', 'variantPrefix' => 'asset/variant'];
        $secret     = ['P_IMAGES_SECRET' => 'secret'];

        return [
            'nothing chosen'     => [[], $secret],
            'no prefix'          => [['variantFilesystem' => 'variants'], $secret],
            'unknown filesystem' => [['variantFilesystem' => 'missing', 'variantPrefix' => 'asset/variant'], $secret],
            'no secret'          => [$configured, []],
            'unsafe prefix'      => [['variantPrefix' => '../x'] + $configured, $secret],
            'invalid size set'   => [['sizeSets' => ['card' => ['widths' => []]]] + $configured, $secret],
            'unknown backend'    => [['backends' => ['default' => 'vips']] + $configured, $secret],
        ];
    }

    #[Test]
    public function buildsAHandlerOnceConfigured(): void
    {
        $settings = new Settings(['variantFilesystem' => 'variants', 'variantPrefix' => 'asset/variant']);

        static::assertInstanceOf(
            VariantRequestHandler::class,
            $this->factory(['P_IMAGES_SECRET' => 'secret'])->handler($settings),
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $env
     */
    #[DataProvider('unusableSettingsProvider')]
    #[Test]
    public function buildsNothingUntilVariantsAreFullyConfigured(array $config, array $env): void
    {
        static::assertNull($this->factory($env)->handler(new Settings($config)));
    }

    #[Test]
    public function buildsNothingWithoutASite(): void
    {
        $factory = new DeliveryFactory(new SitelessCraftEnvironment(new FakeCraftEnvironment(
            $this->createStub(FsInterface::class),
            $this->createStub(Mutex::class),
        )));

        static::assertNull($factory->handler(new Settings([
            'variantFilesystem' => 'variants',
            'variantPrefix'     => 'asset/variant',
        ])));
    }

    /**
     * @param array<string, string> $env
     */
    private function factory(array $env): DeliveryFactory
    {
        return new DeliveryFactory(new FakeCraftEnvironment(
            $this->createStub(FsInterface::class),
            $this->createStub(Mutex::class),
            env: $env,
        ));
    }
}
