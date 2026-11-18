<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\models;

use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\config\SizeSetRows;
use parallelogram\images\config\SizeSetTable;
use parallelogram\images\models\BackendChecks;
use parallelogram\images\models\Settings;
use parallelogram\images\models\SettingsValidator;
use parallelogram\images\models\SingleValueChecks;
use parallelogram\images\models\VariantPrefixCheck;
use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\VariantPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function array_keys;

/**
 * @internal
 */
#[CoversClass(Settings::class)]
#[CoversClass(SettingsValidator::class)]
#[CoversClass(SingleValueChecks::class)]
#[CoversClass(VariantPrefixCheck::class)]
#[CoversClass(BackendChecks::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(AssignmentConfig::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendConfig::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(InvalidBackendException::class)]
#[UsesClass(RemoteSettings::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(EncodingConfig::class)]
#[UsesClass(FieldAssignment::class)]
#[UsesClass(FieldAssignments::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FitConfig::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetConfig::class)]
#[UsesClass(SizeSetRows::class)]
#[UsesClass(SizeSetTable::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(VariantPath::class)]
#[Group('unit')]
final class SettingsTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidSettingsProvider(): array
    {
        return [
            'unknown default format' => [
                ['defaultFormats' => ['gif']],
                'defaultFormats',
                'The default formats include “gif”',
            ],
            'no default formats' => [['defaultFormats' => []], 'defaultFormats', 'Choose at least one default format.'],
            'invalid size set'   => [
                ['sizeSets' => ['card' => ['widths' => [], 'sizes' => '100vw']]],
                'sizeSets',
                '“card” needs at least one width.',
            ],
            'repeated handle'    => [
                [
                    'sizeSetRows' => [
                        ['handle' => 'card', 'widths' => '480', 'sizes' => '1vw'],
                        ['handle' => 'card', 'widths' => '480', 'sizes' => '1vw'],
                    ],
                ],
                'sizeSets',
                'Repeated: card.',
            ],
            'bad assignment'     => [
                ['fieldAssignments' => ['card']],
                'fieldAssignments',
                'Each field assignment needs a field and its size sets.',
            ],
            'quality out of range' => [
                ['formatQuality' => ['avif' => 0]],
                'formatQuality',
                'Avif quality must be a whole number from 1 to 100.',
            ],
            'sharpen too strong' => [['sharpenAmount' => 5.5], 'sharpenAmount', 'Sharpen amount must be from 0 to 5.'],
            'sharpen negative'   => [['sharpenAmount' => -1.0], 'sharpenAmount', 'Sharpen amount must be from 0 to 5.'],
            'too many threads'   => [['threadLimit' => 65], 'threadLimit', 'Thread limit must be from 0 to 64.'],
            'negative threads'   => [['threadLimit' => -1], 'threadLimit', 'Thread limit must be from 0 to 64.'],
            'unsafe prefix'      => [
                ['variantPrefix' => '../secret'],
                'variantPrefix',
                'can\'t be used as a variant prefix',
            ],
            'unknown backend'    => [['backends' => ['default' => 'sharp']], 'backends', '“sharp” isn\'t a backend.'],
            'imgproxy without url'    => [
                ['backends' => ['default' => 'imgproxy', 'imgproxyKey' => '$K', 'imgproxySalt' => '$S']],
                'backends',
                'Enter the address of your imgproxy server.',
            ],
            'literal imgproxy key'    => [
                ['backends' => [
                    'default'      => 'imgproxy',
                    'imgproxyUrl'  => 'https://img.test',
                    'imgproxyKey'  => 'abc',
                    'imgproxySalt' => '$S',
                ]],
                'backends',
                'as environment variables',
            ],
            'literal imgproxy salt'   => [
                [
                    'backends' => [
                        'volumes'      => ['v' => 'imgproxy'],
                        'imgproxyUrl'  => 'https://img.test',
                        'imgproxyKey'  => '$K',
                        'imgproxySalt' => 'abc',
                    ],
                ],
                'backends',
                'as environment variables',
            ],
            'cloudflare without zone' => [
                ['backends' => ['default' => 'cloudflare']],
                'backends',
                'Enter the address of the Cloudflare zone',
            ],
        ];
    }

    #[Test]
    public function acceptsCompleteRemoteBackendSettings(): void
    {
        $settings = new Settings([
            'backends' => [
                'default'           => 'imgproxy',
                'volumes'           => ['volume-1' => 'cloudflare'],
                'imgproxyUrl'       => 'https://img.example.test',
                'imgproxyKey'       => '$IMGPROXY_KEY',
                'imgproxySalt'      => '$IMGPROXY_SALT',
                'cloudflareZoneUrl' => 'https://www.example.test',
            ],
        ]);

        static::assertTrue($settings->validate());
    }

    #[Test]
    public function acceptsSafePrefixesAndLeavesEnvironmentOnesForLater(): void
    {
        static::assertSame(
            [true, true, true],
            [
                (new Settings(['variantPrefix' => 'asset/variant']))->validate(),
                (new Settings(['variantPrefix' => '$VARIANT_PREFIX']))->validate(),
                (new Settings(['variantPrefix' => '@variants']))->validate(),
            ],
        );
    }

    #[Test]
    public function fallsBackToAvifAndJpgWithoutDefaultFormats(): void
    {
        $settings = new Settings(['defaultFormats' => []]);

        static::assertSame([Format::Avif, Format::Jpg], $settings->defaultFormatList());
    }

    #[Test]
    public function isConfiguredOnlyOnceFilesystemAndPrefixAreChosen(): void
    {
        static::assertSame(
            [false, false, false, true],
            [
                (new Settings())->isConfigured(),
                (new Settings(['variantFilesystem' => 'variants']))->isConfigured(),
                (new Settings(['variantPrefix' => 'asset/variant']))->isConfigured(),
                (new Settings(['variantFilesystem' => 'variants', 'variantPrefix' => 'asset/variant']))->isConfigured(),
            ],
        );
    }

    #[Test]
    public function keepsTransientInputOutOfSavedSettings(): void
    {
        $fields = (new Settings())->fields();

        static::assertSame([false, false, true], [
            array_key_exists('sizeSetRows', $fields),
            array_key_exists('fieldAssignmentInput', $fields),
            array_key_exists('sizeSets', $fields),
        ]);
    }

    #[Test]
    public function normalisesValidSettingsAndPrunesDeletedSizeSets(): void
    {
        $settings = new Settings([
            'sizeSets'         => ['card' => ['widths' => '960, 480', 'sizes' => '50vw']],
            'fieldAssignments' => [
                ['fieldUid' => 'field-1', 'sizeSets' => ['card', 'gone']],
                ['fieldUid' => 'field-2', 'sizeSets' => ['gone']],
            ],
        ]);

        static::assertTrue($settings->validate());
        static::assertSame(
            [
                [
                    'card' => [
                        'widths'  => [480, 960],
                        'sizes'   => '50vw',
                        'ratio'   => null,
                        'mode'    => 'crop',
                        'formats' => ['avif', 'jpg'],
                        'quality' => [],
                    ],
                ],
                [['fieldUid' => 'field-1', 'layoutUid' => null, 'sizeSets' => ['card']]],
            ],
            [$settings->sizeSets, $settings->fieldAssignments],
        );
    }

    #[Test]
    public function readsAssignmentsWithoutDeletedSizeSets(): void
    {
        $settings = new Settings([
            'sizeSets'         => ['card' => ['widths' => [480], 'sizes' => '100vw']],
            'fieldAssignments' => [['fieldUid' => 'field-1', 'sizeSets' => ['card', 'gone']]],
        ]);

        static::assertSame(['card'], $settings->assignments()->sizeSetsFor('field-1', layoutUid: null));
    }

    #[Test]
    public function readsPostedFormInputBeforeValidating(): void
    {
        $settings = new Settings([
            'sizeSetRows'          => [['handle' => 'tile', 'widths' => '400', 'sizes' => '25vw', 'jpg' => '1']],
            'fieldAssignmentInput' => ['__submitted' => '1', 'field-1' => ['tile']],
            'formatQuality'        => ['avif' => '55', 'jpg' => ''],
        ]);

        static::assertTrue($settings->validate());
        static::assertSame(
            [['tile'], [['fieldUid' => 'field-1', 'layoutUid' => null, 'sizeSets' => ['tile']]], ['avif' => 55]],
            [array_keys($settings->sizeSets), $settings->fieldAssignments, $settings->formatQuality],
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidSettingsProvider')]
    #[Test]
    public function reportsInvalidSettingsOnTheRightField(array $config, string $attribute, string $message): void
    {
        $settings = new Settings($config);

        static::assertFalse($settings->validate());
        static::assertStringContainsString($message, $settings->getFirstError($attribute) ?? '');
    }

    #[Test]
    public function returnsTheSiteQualityTable(): void
    {
        static::assertSame(50, (new Settings(['formatQuality' => ['avif' => 50]]))->siteQuality()->for(Format::Avif));
    }
}
