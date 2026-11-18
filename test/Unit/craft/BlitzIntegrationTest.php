<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\elements\Asset;
use parallelogram\images\craft\BlitzIntegration;
use parallelogram\images\events\PlaceholderSavedEvent;
use parallelogram\images\Plugin;
use parallelogram\images\test\Trait\StubsAssetsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use putyourlightson\blitz\Blitz;
use yii\base\Event;

/**
 * Blitz isn't a dependency, so the tests that need it load a recording
 * stand-in, which declares classes under Blitz's namespace and so gets a
 * process of its own.
 *
 * @internal
 */
#[CoversClass(BlitzIntegration::class)]
#[UsesClass(PlaceholderSavedEvent::class)]
#[UsesClass(Plugin::class)]
#[Group('unit')]
final class BlitzIntegrationTest extends TestCase
{
    use StubsAssetsTrait;

    #[RunInSeparateProcess]
    #[Test]
    public function addsTheAssetToBlitzsRefreshDataOnceHoweverOftenItIsRegistered(): void
    {
        $blitz = $this->installBlitz();
        BlitzIntegration::register();
        BlitzIntegration::register();

        Event::trigger(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER, new PlaceholderSavedEvent(
            $this->assetWithId(7),
            '#7a8b6c',
        ));

        static::assertSame([[Asset::class, [7]]], $blitz->refreshCache->added);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function doesNothingWhenBlitzIsInstalledButNotEnabled(): void
    {
        $this->loadBlitz();
        Blitz::$plugin = null;
        BlitzIntegration::register();

        Event::trigger(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER, new PlaceholderSavedEvent(
            $this->assetWithId(7),
            '#7a8b6c',
        ));

        static::assertNull(Blitz::$plugin);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function leavesAssetsThatHaveNoIdAlone(): void
    {
        $blitz = $this->installBlitz();
        BlitzIntegration::register();

        Event::trigger(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER, new PlaceholderSavedEvent(
            $this->assetWithId(null),
            '#7a8b6c',
        ));

        static::assertSame([], $blitz->refreshCache->added);
    }

    #[Test]
    public function registersNothingWhenBlitzIsNotInstalled(): void
    {
        BlitzIntegration::register();

        static::assertFalse(Event::hasHandlers(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER));
    }

    private function assetWithId(?int $id): Asset
    {
        $asset     = $this->stubAsset();
        $asset->id = $id;

        return $asset;
    }

    private function installBlitz(): Blitz
    {
        $this->loadBlitz();

        return Blitz::$plugin = new Blitz();
    }

    private function loadBlitz(): void
    {
        require_once __DIR__ . '/../../TestAsset/Fake/Blitz/RefreshCacheService.php';
        require_once __DIR__ . '/../../TestAsset/Fake/Blitz/Blitz.php';
    }
}
