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
        $warmer = Plugin::getInstance()->warmer;
        $audit = $warmer->cachedAudit();
        $coverage = $audit['report'];

        $rows = array_map(static function(array $row) use ($warmer, $coverage): array {
            $row['active'] = $warmer->isActive($row['field'], $row['matrix']);
            $row['coverage'] = $coverage[$row['matrix']] ?? null;
            return $row;
        }, $warmer->summary());

        return Craft::$app->getView()->renderTemplate('image-variants/_utility', [
            'rows' => $rows,
            'anyActive' => $warmer->isActive(null, null),
            'anyAuditing' => $warmer->isActive(null, null, 'audit'),
            'auditedAt' => $audit['auditedAt'],
        ]);
    }
}
