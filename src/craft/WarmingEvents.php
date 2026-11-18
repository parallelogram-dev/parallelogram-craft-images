<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\base\Element;
use craft\elements\Asset;
use craft\events\ModelEvent;
use craft\helpers\Queue;
use parallelogram\images\jobs\DeleteVariantsJob;
use parallelogram\images\jobs\RemoveStaleVariantsJob;
use yii\base\Event;

/**
 * Queues removing an image's variants when the asset is deleted, and its
 * outdated ones when its file is replaced. Variants are never made ahead of
 * time from here.
 *
 * @internal
 */
final class WarmingEvents
{
    public static function queueForDeletion(Event $event): void
    {
        $asset = $event->sender;

        if ($asset instanceof Asset && null !== $asset->uid) {
            Queue::push(new DeleteVariantsJob(['assetUid' => $asset->uid]));
        }
    }

    /**
     * Only a replaced file: a new upload has no variants yet, and nothing is
     * made ahead of time.
     */
    public static function queueForReplacement(ModelEvent $event): void
    {
        $asset = $event->sender;

        if ($asset instanceof Asset && null !== $asset->id && self::isReplacedResizableFile($asset, $event)) {
            Queue::push(new RemoveStaleVariantsJob(['assetId' => $asset->id]));
        }
    }

    /**
     * Safe to call more than once: each handler is only ever attached once.
     */
    public static function register(): void
    {
        $handlers = [
            [Asset::class, Element::EVENT_AFTER_SAVE, [self::class, 'queueForReplacement']],
            [Asset::class, Element::EVENT_AFTER_DELETE, [self::class, 'queueForDeletion']],
        ];

        foreach ($handlers as [$class, $name, $handler]) {
            Event::off($class, $name, $handler);
            Event::on($class, $name, $handler);
        }
    }

    private static function isReplacedResizableFile(Asset $asset, ModelEvent $event): bool
    {
        return ! $event->isNew && NewFile::arrived($asset, $event) && null !== AssetSource::from($asset);
    }
}
