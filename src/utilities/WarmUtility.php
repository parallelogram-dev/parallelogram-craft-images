<?php

namespace parallelogram\imagevariants\utilities;

use Craft;
use craft\base\Utility;
use parallelogram\imagevariants\Plugin;

/**
 * Utilities → Image Variants: per field/matrix asset counts and "Warm" buttons
 * that enqueue a WarmJob, whose progress shows in the CP queue widget.
 */
class WarmUtility extends Utility
{
    public static function id(): string
    {
        return 'image-variants-warm';
    }

    public static function displayName(): string
    {
        return Craft::t('image-variants', 'Image Variants');
    }

    public static function icon(): ?string
    {
        return 'image';
    }

    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('image-variants/_utility', [
            'rows' => Plugin::getInstance()->warmer->summary(),
        ]);
    }
}
