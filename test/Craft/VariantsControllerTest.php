<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use Imagick;
use Override;
use parallelogram\images\controllers\VariantsController;
use parallelogram\images\craft\AssetSource;
use parallelogram\images\sizing\UnknownSizeSetException;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\web\Response;

use function is_file;
use function is_string;

/**
 * Requests variants through the real controller, with real assets in the
 * test volume and variants written to the test filesystem.
 *
 * @internal
 */
#[CoversClass(VariantsController::class)]
#[Group('integration')]
#[Group('craft')]
final class VariantsControllerTest extends TestCase
{
    use UsesCraftAppTrait;

    /**
     * @throws Exception
     */
    private static function secret(): string
    {
        return self::text(App::env('P_IMAGES_SECRET'));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function answersNotFoundForVariantsNoSizeSetDefines(): void
    {
        $this->configureVariants();

        static::assertSame(
            404,
            $this->serve('asset/variant/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/0123456789abcdef/x.avif')->getStatusCode(),
        );
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function answersNotFoundUntilVariantsAreConfigured(): void
    {
        static::assertSame(404, $this->serve('asset/variant/x/y/z.avif')->getStatusCode());
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    #[Test]
    public function generatesAndServesAVariant(): void
    {
        $this->configureVariants();
        $path = $this->variantPath($this->makeImageAsset(ImageFactory::solid(
            400,
            height: 300,
            colour: 'red',
            format: 'jpeg',
        )));

        $response = $this->serve($path);
        $image    = new Imagick();
        $image->pingImageBlob((string) $response->content);

        static::assertSame(
            [200, 'image/avif', 200, true],
            [
                $response->getStatusCode(),
                $response->getHeaders()->get('Content-Type'),
                $image->getImageWidth(),
                is_file(Craft::getAlias('@storage/test-variants') . "/{$path}"),
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

    /**
     * @throws Exception
     */
    private function serve(string $variantPath): Response
    {
        $response = Craft::$app->runAction('p-images/variants/serve', ['variantPath' => $variantPath]);

        return $response instanceof Response ? $response : throw new Exception('The action did not return a response.');
    }

    /**
     * The path the plugin expects for the asset's first "card" variant.
     *
     * @throws Exception
     * @throws UnknownSizeSetException
     */
    private function variantPath(Asset $asset): string
    {
        $settings = $this->plugin()->getSettings();
        $source   = AssetSource::from($asset) ?? throw new Exception('The test asset is not a resizable image.');
        $path     = new VariantPath('asset/variant');
        $planner  = new VariantPlanner(
            new VariantHasher(self::secret(), new Processing()),
            $path,
            $settings->siteQuality(),
        );

        $files = $planner->filesFor($source, $settings->catalogue()->get('card'));

        return ($files[0] ?? throw new Exception('No variant was planned.'))->path;
    }
}
