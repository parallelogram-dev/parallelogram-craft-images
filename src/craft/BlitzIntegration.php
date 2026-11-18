<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\events\PlaceholderSavedEvent;
use parallelogram\images\Plugin;
use putyourlightson\blitz\Blitz;
use yii\base\Event;

use function class_exists;

/**
 * Refreshes Blitz's cached pages that show an image when its placeholder
 * colour is stored, if Blitz is installed. Blitz is never required: with it
 * absent, nothing is registered.
 *
 * @internal
 */
final class BlitzIntegration
{
    /**
     * Adds the asset to Blitz's refresh data; Blitz refreshes the pages that
     * used it when the current request or queue run ends.
     */
    public static function refreshPagesShowing(PlaceholderSavedEvent $event): void
    {
        $id = $event->asset->id;

        if (null !== $id) {
            Blitz::$plugin?->refreshCache->addElementIds(Asset::class, [$id]);
        }
    }

    /**
     * Safe to call more than once: the handler is only ever attached once.
     */
    public static function register(): void
    {
        if (! class_exists(Blitz::class)) {
            return;
        }

        Event::off(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER, [self::class, 'refreshPagesShowing']);
        Event::on(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER, [self::class, 'refreshPagesShowing']);
    }
}
