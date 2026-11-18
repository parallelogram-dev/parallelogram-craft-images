<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\base\Element;
use craft\elements\Asset;
use craft\events\ElementEvent;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use craft\services\Elements;
use parallelogram\images\jobs\DeleteVariantsJob;
use parallelogram\images\jobs\GenerateVariantsJob;
use parallelogram\images\Plugin;
use yii\base\Event;

use function array_values;

/**
 * Queues making variants when an image arrives or is placed in a field with
 * size sets assigned, and removing them when the asset is deleted.
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

    public static function queueForElement(ElementEvent $event): void
    {
        $element = $event->element;
        $warming = Plugin::getInstance()?->warming();

        if (null === $warming || $element instanceof Asset || self::isNotContent($element)) {
            return;
        }

        $changed = $event->isNew ? null : array_values($element->getDirtyFields());

        foreach ($warming->scope->inElement($element, $changed) as $request) {
            Queue::push(new GenerateVariantsJob(['assetId' => $request->assetId, 'sizeSets' => $request->sizeSets]));
        }
    }

    public static function queueForUpload(ModelEvent $event): void
    {
        $asset = $event->sender;

        if ($asset instanceof Asset && null !== $asset->id && self::isNewResizableFile($asset, $event)) {
            Queue::push(new GenerateVariantsJob(['assetId' => $asset->id, 'clean' => ! $event->isNew]));
        }
    }

    /**
     * Safe to call more than once: each handler is only ever attached once.
     */
    public static function register(): void
    {
        $handlers = [
            [Asset::class, Element::EVENT_AFTER_SAVE, [self::class, 'queueForUpload']],
            [Asset::class, Element::EVENT_AFTER_DELETE, [self::class, 'queueForDeletion']],
            [Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, [self::class, 'queueForElement']],
        ];

        foreach ($handlers as [$class, $name, $handler]) {
            Event::off($class, $name, $handler);
            Event::on($class, $name, $handler);
        }
    }

    private static function isNewResizableFile(Asset $asset, ModelEvent $event): bool
    {
        return NewFile::arrived($asset, $event) && null !== AssetSource::from($asset);
    }

    /**
     * Drafts, revisions and the copies made while saving other sites.
     */
    private static function isNotContent(Element $element): bool
    {
        return ElementHelper::isDraftOrRevision($element) || $element->propagating;
    }
}
