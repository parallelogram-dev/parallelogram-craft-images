<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Craft;
use craft\base\Element;
use craft\elements\Asset;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\services\Utilities;
use parallelogram\images\elements\actions\RebuildVariants;
use parallelogram\images\utilities\WarmUtility;
use yii\base\Event;

/**
 * Adds the Utilities page and the Assets index action to the control panel.
 * Both need the same permission, `utility:p-images-warm`, which Craft creates
 * for the utility.
 *
 * @internal
 */
final class ControlPanelEvents
{
    public static function addRebuildAction(RegisterElementActionsEvent $event): void
    {
        if (Craft::$app->getUser()->checkPermission('utility:' . WarmUtility::id())) {
            $event->actions[] = RebuildVariants::class;
        }
    }

    public static function addUtility(RegisterComponentTypesEvent $event): void
    {
        $event->types[] = WarmUtility::class;
    }

    /**
     * Safe to call more than once: each handler is only ever attached once.
     */
    public static function register(): void
    {
        $handlers = [
            [Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, [self::class, 'addUtility']],
            [Asset::class, Element::EVENT_REGISTER_ACTIONS, [self::class, 'addRebuildAction']],
        ];

        foreach ($handlers as [$class, $name, $handler]) {
            Event::off($class, $name, $handler);
            Event::on($class, $name, $handler);
        }
    }
}
