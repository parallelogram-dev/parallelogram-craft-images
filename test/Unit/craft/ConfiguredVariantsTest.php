<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\base\FsInterface;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\craft\ConfiguredVariants;
use parallelogram\images\craft\FsVariantStore;
use parallelogram\images\craft\VariantBackends;
use parallelogram\images\craft\VariantStorage;
use parallelogram\images\models\Settings;
use parallelogram\images\picture\StoredVariantLinks;
use parallelogram\images\remote\ImgproxySigner;
use parallelogram\images\remote\ImgproxyUrls;
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
#[CoversClass(ConfiguredVariants::class)]
#[CoversClass(VariantStorage::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendConfig::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(EncodingConfig::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FitConfig::class)]
#[UsesClass(Format::class)]
#[UsesClass(FsVariantStore::class)]
#[UsesClass(ImgproxySigner::class)]
#[UsesClass(ImgproxyUrls::class)]
#[UsesClass(InvalidBackendException::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(RemoteSettings::class)]
#[UsesClass(Settings::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetConfig::class)]
#[UsesClass(StoredVariantLinks::class)]
#[UsesClass(VariantBackends::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[Group('unit')]
final class ConfiguredVariantsTest extends TestCase
{
    private const array CONFIGURED = ['variantFilesystem' => 'variants', 'variantPrefix' => 'asset/variant'];

    /**
     * @return array<string, array{array<string, mixed>, array<string, string>}>
     */
    public static function unusableSettingsProvider(): array
    {
        $secret = ['P_IMAGES_SECRET' => 'secret'];

        return [
            'nothing chosen'     => [[], $secret],
            'no prefix'          => [['variantFilesystem' => 'variants'], $secret],
            'unknown filesystem' => [['variantFilesystem' => 'missing', 'variantPrefix' => 'asset/variant'], $secret],
            'no secret'          => [self::CONFIGURED, []],
            'unsafe prefix'      => [['variantPrefix' => '../x'] + self::CONFIGURED, $secret],
            'invalid size set'   => [['sizeSets' => ['card' => ['widths' => []]]] + self::CONFIGURED, $secret],
            'unknown backend'    => [['backends' => ['default' => 'sharp']] + self::CONFIGURED, $secret],
        ];
    }

    #[Test]
    public function buildsEverythingOnceConfigured(): void
    {
        $variants = ConfiguredVariants::from(
            new Settings(['sizeSets' => ['card' => ['widths' => [480], 'sizes' => '100vw']]] + self::CONFIGURED),
            $this->environment(['P_IMAGES_SECRET' => 'secret']),
        );

        static::assertSame(['card'], $variants?->catalogue->handles());
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $env
     */
    #[DataProvider('unusableSettingsProvider')]
    #[Test]
    public function buildsNothingUntilVariantsAreFullyConfigured(array $config, array $env): void
    {
        static::assertNull(ConfiguredVariants::from(new Settings($config), $this->environment($env)));
    }

    #[Test]
    public function buildsNothingWithoutASite(): void
    {
        $environment = new SitelessCraftEnvironment($this->environment(['P_IMAGES_SECRET' => 'secret']));

        static::assertNull(ConfiguredVariants::from(new Settings(self::CONFIGURED), $environment));
    }

    #[Test]
    public function resolvesRemoteServiceSettingsFromTheEnvironment(): void
    {
        $variants = ConfiguredVariants::from(
            new Settings([
                'backends' => [
                    'default'      => 'imgproxy',
                    'imgproxyUrl'  => '$IMGPROXY_URL',
                    'imgproxyKey'  => '$IMGPROXY_KEY',
                    'imgproxySalt' => '$IMGPROXY_SALT',
                ],
            ] + self::CONFIGURED),
            $this->environment([
                'P_IMAGES_SECRET' => 'secret',
                'IMGPROXY_URL'    => 'https://img.example.test',
                'IMGPROXY_KEY'    => 'abcd',
                'IMGPROXY_SALT'   => 'ef01',
            ]),
        );

        static::assertInstanceOf(ImgproxyUrls::class, $variants?->backends->serviceFor(volumeUid: null));
    }

    /**
     * @param array<string, string> $env
     */
    private function environment(array $env): FakeCraftEnvironment
    {
        return new FakeCraftEnvironment(
            $this->createStub(FsInterface::class),
            $this->createStub(Mutex::class),
            env: $env,
        );
    }
}
