<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\sizing\InvalidSizeSetException;

/**
 * Checks the settings that each stand alone: field assignment shape, site
 * quality, sharpening, threads and the variant prefix. One message per
 * setting.
 *
 * @internal
 */
final class SingleValueChecks
{
    /**
     * @return array<string, string>
     */
    public static function errors(Settings $settings): array
    {
        $errors = [];

        try {
            AssignmentConfig::fromArray($settings->fieldAssignments);
        } catch (InvalidSizeSetException $exception) {
            $errors['fieldAssignments'] = $exception->getMessage();
        }

        try {
            $settings->siteQuality();
        } catch (InvalidSizeSetException $exception) {
            $errors['formatQuality'] = $exception->getMessage();
        }

        if ($settings->sharpenAmount < 0.0 || $settings->sharpenAmount > 5.0) {
            $errors['sharpenAmount'] = 'Sharpen amount must be from 0 to 5.';
        }

        if ($settings->threadLimit < 0 || $settings->threadLimit > 64) {
            $errors['threadLimit'] = 'Thread limit must be from 0 to 64.';
        }

        $prefixError = VariantPrefixCheck::error($settings->variantPrefix);

        if (null !== $prefixError) {
            $errors['variantPrefix'] = $prefixError;
        }

        return $errors;
    }
}
