<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\base\FsInterface;
use parallelogram\images\backends\BackendAvailability;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\backends\VolumeRoutingGenerator;
use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\craft\ConfiguredVariants;
use parallelogram\images\craft\CraftSourceFile;
use parallelogram\images\craft\CraftSourceLookup;
use parallelogram\images\craft\DeliveryFactory;
use parallelogram\images\craft\FsVariantStore;
use parallelogram\images\craft\MutexVariantLock;
use parallelogram\images\craft\SizeSetScope;
use parallelogram\images\craft\VariantBackends;
use parallelogram\images\craft\VariantStorage;
use parallelogram\images\craft\Warming;
use parallelogram\images\craft\WarmingFactory;
use parallelogram\images\delivery\VariantMaker;
use parallelogram\images\models\Settings;
use parallelogram\images\picture\StoredVariantLinks;
use parallelogram\images\processing\gd\GdGenerator;
use parallelogram\images\processing\ImagickGenerator;
use parallelogram\images\processing\vips\VipsGenerator;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\test\TestAsset\Fake\FakeCraftEnvironment;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use parallelogram\images\warming\VariantCleaner;
use parallelogram\images\warming\VariantWarmer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use yii\mutex\Mutex;

/**
 * @internal
 */
#[CoversClass(WarmingFactory::class)]
#[UsesClass(AssignmentConfig::class)]
#[UsesClass(BackendAvailability::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendConfig::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(ConfiguredVariants::class)]
#[UsesClass(CraftSourceFile::class)]
#[UsesClass(CraftSourceLookup::class)]
#[UsesClass(DeliveryFactory::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(EncodingConfig::class)]
#[UsesClass(FieldAssignments::class)]
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
#[UsesClass(RemoteSettings::class)]
#[UsesClass(Settings::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetConfig::class)]
#[UsesClass(SizeSetScope::class)]
#[UsesClass(StoredVariantLinks::class)]
#[UsesClass(VariantBackends::class)]
#[UsesClass(VariantCleaner::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantMaker::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[UsesClass(VariantStorage::class)]
#[UsesClass(VariantWarmer::class)]
#[UsesClass(VipsGenerator::class)]
#[UsesClass(VolumeRoutingGenerator::class)]
#[UsesClass(Warming::class)]
#[Group('unit')]
final class WarmingFactoryTest extends TestCase
{
    private const array CONFIGURED = ['variantFilesystem' => 'variants', 'variantPrefix' => 'asset/variant'];

    #[Test]
    public function buildsNothingUntilVariantsAreConfigured(): void
    {
        static::assertNull($this->factory()->warming(new Settings()));
    }

    #[Test]
    public function buildsNothingWhileFieldAssignmentsAreInvalid(): void
    {
        static::assertNull($this->factory()->warming(new Settings(['fieldAssignments' => ['card']]
            + self::CONFIGURED)));
    }

    #[Test]
    public function buildsWarmingOnceConfigured(): void
    {
        static::assertInstanceOf(Warming::class, $this->factory()->warming(new Settings(self::CONFIGURED)));
    }

    private function factory(): WarmingFactory
    {
        return new WarmingFactory(new FakeCraftEnvironment(
            $this->createStub(FsInterface::class),
            $this->createStub(Mutex::class),
        ));
    }
}
