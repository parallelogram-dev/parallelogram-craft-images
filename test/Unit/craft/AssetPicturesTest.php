<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\base\FsInterface;
use craft\elements\Asset;
use craft\elements\ElementCollection;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\craft\ArtDirectionOption;
use parallelogram\images\craft\AssetFiles;
use parallelogram\images\craft\AssetPictures;
use parallelogram\images\craft\AssetSource;
use parallelogram\images\craft\ConfiguredVariants;
use parallelogram\images\craft\FsVariantStore;
use parallelogram\images\craft\PictureSourcesFinder;
use parallelogram\images\craft\PlaceholderStore;
use parallelogram\images\craft\RelatedAsset;
use parallelogram\images\craft\VariantBackends;
use parallelogram\images\craft\VariantStorage;
use parallelogram\images\models\Settings;
use parallelogram\images\picture\ArtDirection;
use parallelogram\images\picture\ElementAttributes;
use parallelogram\images\picture\FormatSource;
use parallelogram\images\picture\HtmlAttributes;
use parallelogram\images\picture\InvalidPictureException;
use parallelogram\images\picture\Loading;
use parallelogram\images\picture\OptionReader;
use parallelogram\images\picture\OriginalImage;
use parallelogram\images\picture\PictureMarkup;
use parallelogram\images\picture\PictureOptions;
use parallelogram\images\picture\PictureSources;
use parallelogram\images\picture\PictureSourcesBuilder;
use parallelogram\images\picture\RemoteVariantLinks;
use parallelogram\images\picture\StoredVariantLinks;
use parallelogram\images\processing\Rect;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\remote\CloudflareUrls;
use parallelogram\images\sizing\AlternateImage;
use parallelogram\images\sizing\AlternateImages;
use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\sizing\UnknownSizeSetException;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Fake\FakeCraftEnvironment;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use yii\mutex\Mutex;

use function count;
use function substr_count;

/**
 * @internal
 */
#[CoversClass(AssetPictures::class)]
#[CoversClass(PictureSourcesFinder::class)]
#[CoversClass(AssetFiles::class)]
#[UsesClass(AlternateImage::class)]
#[UsesClass(AlternateImages::class)]
#[UsesClass(ArtDirection::class)]
#[UsesClass(ArtDirectionOption::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(AssetSource::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendConfig::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(Box::class)]
#[UsesClass(CloudflareUrls::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(ConfiguredVariants::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(EncodingConfig::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FitConfig::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(ElementAttributes::class)]
#[UsesClass(FormatSource::class)]
#[UsesClass(FsVariantStore::class)]
#[UsesClass(HtmlAttributes::class)]
#[UsesClass(InvalidPictureException::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(OptionReader::class)]
#[UsesClass(OriginalImage::class)]
#[UsesClass(PictureMarkup::class)]
#[UsesClass(PictureOptions::class)]
#[UsesClass(PictureSources::class)]
#[UsesClass(PictureSourcesBuilder::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(Rect::class)]
#[UsesClass(PlaceholderStore::class)]
#[UsesClass(RelatedAsset::class)]
#[UsesClass(RemoteSettings::class)]
#[UsesClass(RemoteVariantLinks::class)]
#[UsesClass(ResizePlan::class)]
#[UsesClass(Settings::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetConfig::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(StoredVariantLinks::class)]
#[UsesClass(UnknownSizeSetException::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantBackends::class)]
#[UsesClass(VariantFile::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[UsesClass(VariantStorage::class)]
#[Group('unit')]
final class AssetPicturesTest extends TestCase
{
    use StubsAssetsTrait;

    private const string ALTERNATE_FIELD = '60f35b38-630a-49b5-9709-8c5355aaed13';

    private const string ART_UID = '1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f';

    private const string VARIANT_URL = 'https://www\.example\.test/asset/variant/[0-9a-f-]{36}/[0-9a-f]{16}/';

    /**
     * @var list<string>
     */
    private array $warnings = [];

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function addsABlankSourceThatShowsNothingOnSmallScreens(): void
    {
        $html = $this->pictures()->picture($this->stubAsset(), sizeSet: 'card', options: [
            'sources' => [['media' => '(max-width: 599px)', 'blank' => true]],
        ]);

        static::assertStringContainsString(
            '<source media="(max-width: 599px)" srcset="data:image/gif;base64,',
            $html,
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function addsArtDirectedSourcesForOtherAssetsAndSizeSets(): void
    {
        $html = $this->pictures()->picture($this->stubAsset(), sizeSet: 'card', options: [
            'sources' => [
                [
                    'media'   => '(max-width: 600px)',
                    'asset'   => $this->stubAsset(['uid' => self::ART_UID]),
                    'sizeSet' => 'square',
                ],
                ['media' => '(min-width: 2000px)'],
            ],
        ]);

        static::assertSame(
            [1, 2, 2],
            [
                substr_count(
                    $html,
                    needle: 'media="(max-width: 600px)" type="image/avif" srcset="https://www.example.test/asset/variant/'
                        . self::ART_UID,
                ),
                substr_count($html, needle: 'sizes="50vw"'),
                substr_count($html, needle: 'media="(min-width: 2000px)"'),
            ],
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function addsNoAlternateWhenTheFieldIsEmpty(): void
    {
        $asset = $this->stubAsset([
            'layoutFields' => [self::ALTERNATE_FIELD => 'mobileAltImage'],
            'fieldValues'  => ['mobileAltImage' => new ElementCollection()],
        ]);

        static::assertStringNotContainsString(
            'media=',
            $this->picturesWithAlternate()->picture($asset, sizeSet: 'card'),
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function addsTheSizeSetsAlternateImage(): void
    {
        $html = $this->picturesWithAlternate()->picture($this->withAlternate(), sizeSet: 'card');

        static::assertSame(
            [2, 2],
            [
                substr_count($html, needle: 'media="(max-width: 430px)" type="image/'),
                substr_count($html, needle: 'srcset="https://www.example.test/asset/variant/' . self::ART_UID),
            ],
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function errsInDevModeOverAnUnknownSizeSet(): void
    {
        $this->expectException(UnknownSizeSetException::class);

        $this->pictures()->picture($this->stubAsset(), sizeSet: 'hero');
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function errsInDevModeOverAnUnknownSizeSetsVariants(): void
    {
        $this->expectException(UnknownSizeSetException::class);

        $this->pictures()->variants($this->stubAsset(), sizeSet: 'hero');
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function errsWhenAnImageServiceCantFetchTheOriginal(): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('has no public URL');

        $this->pictures(['backends' => ['default' => 'cloudflare', 'cloudflareZoneUrl' => 'https://cdn.test']])
            ->picture($this->stubAsset(['url' => null]), sizeSet: 'card');
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function explainsInDevModeThatVariantsAreOff(): void
    {
        static::assertSame(
            '<!-- Parallelogram Images: variants are off until a variant filesystem and prefix are chosen, so this is '
                . 'the original file. --><img src="/uploads/harbour.jpg" width="2000" height="1500" alt="Harbour at dawn" '
                . 'loading="lazy" decoding="async">',
            $this->unconfigured(devMode: true)->picture($this->stubAsset(), sizeSet: 'card'),
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function explainsInDevModeWhyAFileIsShownAsUploaded(): void
    {
        static::assertStringStartsWith(
            '<!-- Parallelogram Images: this file is shown as uploaded',
            $this->pictures()->picture($this->stubAsset(['filename' => 'logo.svg']), sizeSet: 'card'),
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function givesAssetsWithoutAltTextAnEmptyAlt(): void
    {
        static::assertStringContainsString(
            'alt=""',
            $this->pictures()->picture($this->stubAsset(['alt' => null]), sizeSet: 'card'),
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function hasNoVariantsUntilConfigured(): void
    {
        static::assertNull($this->unconfigured(devMode: true)->variants($this->stubAsset(), sizeSet: 'card'));
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function keepsTheAltTextWhenAProductionPictureFallsBack(): void
    {
        static::assertStringContainsString(
            'alt="Harbour at dawn"',
            $this->productionPictures()->picture($this->stubAsset(), sizeSet: 'hero'),
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function letsTemplateSourcesReplaceTheAlternate(): void
    {
        $html = $this->picturesWithAlternate()->picture($this->withAlternate(), sizeSet: 'card', options: [
            'sources' => [],
        ]);

        static::assertStringNotContainsString('media=', $html);
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function linksAssetsWhoseVolumeCantBeFoundToTheVariantFilesystem(): void
    {
        $html = $this->pictures([
            'backends' => ['volumes' => ['volume-1' => 'cloudflare'], 'cloudflareZoneUrl' => 'https://cdn.test'],
        ])->picture($this->stubAsset(['volumeUid' => null]), sizeSet: 'card');

        static::assertStringContainsString('srcset="https://www.example.test/asset/variant/', $html);
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function linksRemoteVolumesToTheirImageService(): void
    {
        $html = $this->pictures([
            'backends' => ['volumes' => ['volume-1' => 'cloudflare'], 'cloudflareZoneUrl' => 'https://cdn.test'],
        ])->picture($this->stubAsset(), sizeSet: 'card');

        static::assertStringContainsString('srcset="https://cdn.test/cdn-cgi/image/width=480,', $html);
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function logsProblemsInProductionAndShowsTheOriginal(): void
    {
        $html = $this->productionPictures()->picture($this->stubAsset(['alt' => null]), sizeSet: 'card', options: [
            'lazy' => true,
        ]);

        static::assertSame(
            [
                '<img src="/uploads/harbour.jpg" width="2000" height="1500" alt="" loading="lazy" decoding="async">',
                [
                    'Unknown picture option: lazy. Use alt, attributes, class, lazysrc, pictureAttributes, pictureClass, priority, sizes, sources.',
                ],
            ],
            [$html, $this->warnings],
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function logsUnknownSizeSetsInProductionAndReturnsNoVariants(): void
    {
        static::assertSame(
            [null, 1],
            [$this->productionPictures()->variants($this->stubAsset(), sizeSet: 'hero'), count($this->warnings)],
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function logsUnreachableRemoteOriginalsInProductionAndReturnsNoVariants(): void
    {
        $pictures = $this->build(
            ['backends' => ['default' => 'cloudflare', 'cloudflareZoneUrl' => 'https://cdn.test']],
            devMode: false,
        );

        static::assertSame(
            [null, 1],
            [$pictures->variants($this->stubAsset(['url' => null]), sizeSet: 'card'), count($this->warnings)],
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function returnsTheVariantsForTemplatesToUse(): void
    {
        $variants = $this->pictures()->variants($this->stubAsset(), sizeSet: 'card');

        static::assertMatchesRegularExpression(
            '#^' . self::VARIANT_URL . 'harbour\.avif 480w, #',
            $variants?->srcset('avif') ?? '',
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function showsTheOriginalWithoutCommentInProductionUntilConfigured(): void
    {
        static::assertStringStartsWith(
            '<img src="/uploads/harbour.jpg"',
            $this->unconfigured(devMode: false)->picture($this->stubAsset(), sizeSet: 'card'),
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function showsTheOriginalWithoutCommentInProductionWhenNotResized(): void
    {
        static::assertStringStartsWith(
            '<img src="/uploads/harbour.gif"',
            $this->productionPictures()->picture(
                $this->stubAsset(['filename' => 'harbour.gif', 'url' => '/uploads/harbour.gif', 'volumeUid' => null]),
                sizeSet: 'card',
            ),
        );
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function usesTheSitesDefaultLoading(): void
    {
        $html = $this->pictures(loading: Loading::Lazysrc)->picture($this->stubAsset(), sizeSet: 'card');

        static::assertStringContainsString('<img data-lazysrc data-lazysrc-src=', $html);
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function writesAPictureOfTheVariants(): void
    {
        $html = $this->pictures()->picture($this->stubAsset(), sizeSet: 'card', options: ['class' => 'hero']);

        static::assertMatchesRegularExpression(
            '#^<picture><source type="image/avif" srcset="' . self::VARIANT_URL . 'harbour\.avif 480w, #',
            $html,
        );
        static::assertStringEndsWith(
            'sizes="100vw" width="960" height="720" alt="Harbour at dawn" class="hero" loading="lazy" decoding="async"></picture>',
            $html,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function build(
        array $config,
        bool $devMode,
        Loading $loading = Loading::Lazy,
        AlternateImages $alternates = new AlternateImages(),
    ): AssetPictures {
        $settings = new Settings(
            $config
                + [
                    'variantFilesystem' => 'variants',
                    'variantPrefix'     => 'asset/variant',
                    'sizeSets'          => [
                        'card'   => ['widths' => [480, 960], 'sizes' => '100vw'],
                        'square' => ['widths' => [480], 'sizes' => '50vw', 'ratio' => '1:1'],
                    ],
                ],
        );

        return new AssetPictures(
            ConfiguredVariants::from($settings, new FakeCraftEnvironment(
                $this->createStub(FsInterface::class),
                $this->createStub(Mutex::class),
            )),
            $devMode,
            function (string $message): void {
                $this->warnings[] = $message;
            },
            $loading,
            $alternates,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function pictures(array $config = [], Loading $loading = Loading::Lazy): AssetPictures
    {
        return $this->build($config, devMode: true, loading: $loading);
    }

    /**
     * Pictures whose "card" size set shows the asset in the alternate field
     * on phones, in the "square" size set.
     */
    private function picturesWithAlternate(): AssetPictures
    {
        return $this->build([], devMode: true, alternates: new AlternateImages([
            'card' => new AlternateImage(self::ALTERNATE_FIELD, '(max-width: 430px)', 'square'),
        ]));
    }

    private function productionPictures(): AssetPictures
    {
        return $this->build([], devMode: false);
    }

    private function unconfigured(bool $devMode): AssetPictures
    {
        return new AssetPictures(null, $devMode, static function (string $_message): void {});
    }

    private function withAlternate(): Asset
    {
        return $this->stubAsset([
            'layoutFields' => [self::ALTERNATE_FIELD => 'mobileAltImage'],
            'fieldValues'  => ['mobileAltImage' => new ElementCollection([$this->stubAsset(['uid' => self::ART_UID])])],
        ]);
    }
}
