<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\base\Element;
use craft\elements\Asset;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Queue;
use craft\services\Fields;
use parallelogram\images\fields\PlaceholderColourField;
use parallelogram\images\jobs\PlaceholderJob;
use yii\base\Event;

/**
 * Registers the placeholder field type, and queues a colour for each image
 * uploaded or replaced in a volume whose layout has the field.
 *
 * @internal
 */
final class PlaceholderEvents
{
    public static function addFieldType(RegisterComponentTypesEvent $event): void
    {
        $event->types[] = PlaceholderColourField::class;
    }

    public static function queueForSavedAsset(ModelEvent $event): void
    {
        $asset = $event->sender;

        if ($asset instanceof Asset && null !== $asset->id && self::needsColour($asset, $event)) {
            Queue::push(new PlaceholderJob(['assetId' => $asset->id]));
        }
    }

    /**
     * Safe to call more than once: each handler is only ever attached once.
     */
    public static function register(): void
    {
        $handlers = [
            [Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, [self::class, 'addFieldType']],
            [Asset::class, Element::EVENT_AFTER_SAVE, [self::class, 'queueForSavedAsset']],
        ];

        foreach ($handlers as [$class, $name, $handler]) {
            Event::off($class, $name, $handler);
            Event::on($class, $name, $handler);
        }
    }

    /**
     * Whether a new file was just stored and the asset can hold a colour.
     */
    private static function needsColour(Asset $asset, ModelEvent $event): bool
    {
        $hasNewFile = $event->isNew || Asset::SCENARIO_REPLACE === $asset->getScenario();

        return $hasNewFile && null !== AssetSource::from($asset) && null !== PlaceholderStore::field($asset);
    }
}
