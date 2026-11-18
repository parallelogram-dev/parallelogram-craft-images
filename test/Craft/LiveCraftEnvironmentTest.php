<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use craft\fs\Local;
use Override;
use parallelogram\images\craft\LiveCraftEnvironment;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\mutex\Mutex;

use function str_starts_with;

/**
 * @internal
 */
#[CoversClass(LiveCraftEnvironment::class)]
#[Group('integration')]
#[Group('craft')]
final class LiveCraftEnvironmentTest extends TestCase
{
    use UsesCraftAppTrait;

    private const string MISSING_UID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    /**
     * @throws Exception
     * @throws Throwable
     */
    #[Test]
    public function findsAssetsAndTheirVolumes(): void
    {
        $asset       = $this->makeImageAsset(ImageFactory::solid(40, height: 20, colour: 'red', format: 'jpeg'));
        $environment = new LiveCraftEnvironment();
        $volumeUid   = Craft::$app->getVolumes()->getVolumeByHandle('testImages')?->uid;

        static::assertSame(
            [$asset->id, $volumeUid, null, null],
            [
                $environment->findAsset((string) $asset->uid)?->id,
                $environment->volumeOf((string) $asset->uid),
                $environment->findAsset(self::MISSING_UID),
                $environment->volumeOf(self::MISSING_UID),
            ],
        );
    }

    #[Test]
    public function findsFilesystemsByHandle(): void
    {
        $environment = new LiveCraftEnvironment();

        static::assertSame(
            [true, null],
            [$environment->filesystem('testVariants') instanceof Local, $environment->filesystem('missing')],
        );
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function readsTheRunningSite(): void
    {
        $environment = new LiveCraftEnvironment();

        static::assertSame(
            [true, true, false, true, true, true],
            [
                str_starts_with($environment->siteUrl(), 'http'),
                '' !== $environment->parse('$P_IMAGES_SECRET'),
                $environment->hasLibrary(''),
                $environment->hasLibrary('gd'),
                $environment->mutex() instanceof Mutex,
                $environment->hasLibrary('vips'),
            ],
        );
    }

    #[Test]
    public function returnsAnEmptyStringForSettingsThatAreNotText(): void
    {
        static::assertSame('', (new LiveCraftEnvironment())->parse('$P_IMAGES_TEST_UNSET_VARIABLE'));
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
}
