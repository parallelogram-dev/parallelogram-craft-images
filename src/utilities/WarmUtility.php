<?php

namespace parallelogram\images\utilities;

use Craft;
use craft\base\Utility;
use parallelogram\images\Plugin;

/**
 * Utilities → Parallelogram Images: per field/matrix asset counts and "Warm" buttons
 * that enqueue a WarmJob, whose progress shows in the CP queue widget.
 */
class WarmUtility extends Utility
{
    public static function contentHtml(): string
    {
        $warmer   = Plugin::getInstance()->warmer;
        $audit    = $warmer->cachedAudit();
        $coverage = $audit['report'];

        $rows = array_map(static function (array $row) use ($warmer, $coverage): array {
            $row['active']   = $warmer->isActive($row['field'], $row['matrix']);
            $row['coverage'] = $coverage[$row['matrix']] ?? null;
            return $row;
        }, $warmer->summary());

        return Craft::$app->getView()
            ->renderTemplate('p-images/_utility', [
                'rows'        => $rows,
                'anyActive'   => $warmer->isActive(null, null),
                'anyAuditing' => $warmer->isActive(null, null, 'audit'),
                'auditedAt'   => $audit['auditedAt'],
            ]);
    }

    public static function displayName(): string
    {
        return Craft::t('p-images', 'Parallelogram Images');
    }

    public static function icon(): ?string
    {
        return 'image';
    }

    public static function id(): string
    {
        return 'p-images-warm';
    }
}
