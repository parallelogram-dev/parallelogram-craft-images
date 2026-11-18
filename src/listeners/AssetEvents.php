<?php

namespace parallelogram\images\listeners;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\events\AssetEvent;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use parallelogram\images\Plugin;

/**
 * Drives synchronous, assignment-scoped generation when content changes — never
 * the queue. Bulk/import (console) contexts are skipped and deferred to the warm
 * command.
 */
class AssetEvents
{
    /**
     * When an asset's file is replaced, regenerate the matrices for its volume,
     * so coverage is complete even before any element references the asset.
     */
    public static function handleAssetReplace(AssetEvent $event): void
    {
        $matrixHandles = Plugin::getInstance()->warmer->matrixHandlesForVolume($event->asset);

        if ([] !== $matrixHandles) {
            Plugin::getInstance()->generator->generate($event->asset, $matrixHandles);
        }
    }

    /**
     * When an element is saved in a web context, generate the assigned matrices
     * for assets in any assigned Asset field that was touched.
     *
     * A newly uploaded image is itself an element save with no relations yet, so
     * it is generated up front (volume-scoped) rather than waiting for an entry
     * to reference it — closing the gap where a fresh upload renders before its
     * variants exist.
     */
    public static function handleElementSave(ElementEvent $event): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        $element = $event->element;

        if (! $element instanceof ElementInterface) {
            return;
        }

        if (ElementHelper::isDraftOrRevision($element)) {
            return;
        }

        if ($element instanceof Asset) {
            if ($event->isNew && Asset::KIND_IMAGE === $element->kind) {
                $matrixHandles = Plugin::getInstance()->warmer->matrixHandlesForVolume($element);

                if ([] !== $matrixHandles) {
                    Plugin::getInstance()->generator->generate($element, $matrixHandles);
                }
            }

            return;
        }

        $layout = $element->getFieldLayout();
        if (null === $layout) {
            return;
        }

        $settings  = Plugin::getInstance()->getSettings();
        $generator = Plugin::getInstance()->generator;
        $dirty     = $element->getDirtyFields();

        foreach ($settings->fieldAssignments as $fieldHandle => $matrixHandles) {
            if ($layout->getFieldByHandle($fieldHandle) === null) {
                continue;
            }

            if (! $event->isNew && ! in_array($fieldHandle, $dirty, true)) {
                continue;
            }

            $value = $element->getFieldValue($fieldHandle);
            if (! $value instanceof AssetQuery) {
                continue;
            }

            foreach ($value->all() as $asset) {
                $generator->generate($asset, $matrixHandles);
            }
        }
    }
}
