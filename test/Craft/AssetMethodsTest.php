<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use craft\elements\Asset;
use craft\web\View;
use Override;
use parallelogram\images\craft\AssetMethods;
use parallelogram\images\craft\AssetPictures;
use parallelogram\images\Plugin;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;

use function str_contains;

/**
 * `asset.picture()` and `asset.variants()` in real templates.
 *
 * @internal
 */
#[CoversClass(AssetMethods::class)]
#[CoversClass(Plugin::class)]
#[Group('integration')]
#[Group('craft')]
final class AssetMethodsTest extends TestCase
{
    use UsesCraftAppTrait;

    /**
     * @throws Throwable
     */
    #[Test]
    public function followsTheSitesLazysrcDefault(): void
    {
        $this->configureVariants(['lazysrcByDefault' => true]);

        static::assertStringContainsString('<img data-lazysrc ', $this->render("{{ asset.picture('card') }}"));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function keepsRenderingWithoutAPluginInstance(): void
    {
        $this->configureVariants();
        $original = Plugin::getInstance();
        Plugin::setInstance(null);

        try {
            $html = $this->render("{{ asset.picture('card') }}");
        } finally {
            Plugin::setInstance($original);
        }

        static::assertStringContainsString('<img ', $html);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function logsAnUnknownSizeSetInProductionAndShowsTheOriginal(): void
    {
        $general          = Craft::$app->getConfig()->getGeneral();
        $devMode          = $general->devMode;
        $general->devMode = false;

        try {
            $this->configureVariants();
            $html = $this->render("{{ asset.picture('missing', {alt: 'Red'}) }}");
        } finally {
            $general->devMode = $devMode;
        }

        static::assertStringStartsWith('<img src=', $html);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function readsTheVariantsOfAnAsset(): void
    {
        $this->configureVariants();

        static::assertMatchesRegularExpression(
            '#^/test-variants/asset/variant/[0-9a-f-]{36}/[0-9a-f]{16}/harbour\.avif 200w$#',
            $this->render("{{ asset.variants('card').srcset('avif') }}"),
        );
    }

    /**
     * Registers the methods again exactly as the plugin does, falling back to
     * the booted plugin, so later tests see no difference.
     *
     * @throws Exception
     */
    #[Test]
    public function registersPictureAndVariantsOnAssets(): void
    {
        $plugin = $this->plugin();
        AssetMethods::register(static fn(): AssetPictures => (Plugin::getInstance() ?? $plugin)->pictures());

        $asset = new Asset();

        static::assertSame([true, true], [$asset->hasMethod('picture'), $asset->hasMethod('variants')]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function showsVariantsOnceSettingsChange(): void
    {
        $asset  = $this->makeImageAsset(ImageFactory::solid(400, height: 300, colour: 'red', format: 'jpeg'));
        $before = $this->render("{{ asset.picture('card', {alt: 'Red'}) }}", $asset);
        $this->configureVariants();

        static::assertSame(
            [false, true],
            [
                str_contains($before, '/asset/variant/'),
                str_contains($this->render("{{ asset.picture('card', {alt: 'Red'}) }}", $asset), 'alt="Red"'),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function writesUnescapedPictureMarkup(): void
    {
        $this->configureVariants();

        static::assertMatchesRegularExpression(
            '#^<picture><img src="/test-variants/asset/variant/[^"]+/harbour\.avif" srcset="#',
            $this->render("{{ asset.picture('card') }}"),
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
    private function render(string $template, ?Asset $asset = null): string
    {
        /** @var View $view Craft's view is always its web view. */
        $view  = Craft::$app->getView();
        $asset ??= $this->makeImageAsset(ImageFactory::solid(400, height: 300, colour: 'red', format: 'jpeg'));

        return $view->renderString($template, ['asset' => $asset]);
    }
}
