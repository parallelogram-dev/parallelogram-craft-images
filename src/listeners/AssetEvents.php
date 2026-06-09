<?php

namespace parallelogram\imagevariants\listeners;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\events\AssetEvent;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use parallelogram\imagevariants\Plugin;

/**
 * Drives synchronous, assignment-scoped generation when content changes — never
 * the queue. Bulk/import (console) contexts are skipped and deferred to the warm
 * command.
 */
class AssetEvents
{
    /**
     * When an element is saved in a web context, generate the assigned matrices
     * for assets in any assigned Asset field that was touched.
     */
    public static function handleElementSave(ElementEvent $event): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        $element = $event->element;

        if (!$element instanceof ElementInterface || $element instanceof Asset) {
            return;
        }

        if (ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        $layout = $element->getFieldLayout();
        if ($layout === null) {
            return;
        }

        $settings = Plugin::getInstance()->getSettings();
        $generator = Plugin::getInstance()->generator;
        $dirty = $element->getDirtyFields();

        foreach ($settings->fieldAssignments as $fieldHandle => $matrixHandles) {
            if ($layout->getFieldByHandle($fieldHandle) === null) {
                continue;
            }

            if (!$event->isNew && !in_array($fieldHandle, $dirty, true)) {
                continue;
            }

            $value = $element->getFieldValue($fieldHandle);
            if (!$value instanceof AssetQuery) {
                continue;
            }

            foreach ($value->all() as $asset) {
                $generator->generate($asset, $matrixHandles);
            }
        }
    }

    /**
     * When an asset's file is replaced, regenerate the matrices for every
     * assigned field that references it.
     */
    public static function handleAssetReplace(AssetEvent $event): void
    {
        $matrixHandles = Plugin::getInstance()->warmer->matrixHandlesForAsset($event->asset);

        if ($matrixHandles !== []) {
            Plugin::getInstance()->generator->generate($event->asset, $matrixHandles);
        }
    }
}
