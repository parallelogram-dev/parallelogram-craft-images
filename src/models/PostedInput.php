<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\config\AlternateRows;
use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\SizeSetRows;

use function array_key_exists;

/**
 * Converts what the settings screen posts into stored settings before they
 * are validated: table rows into size sets and alternate images, checkboxes
 * into field assignments, and quality boxes into numbers. Each is only
 * converted when it was posted, so settings saved from code are untouched.
 *
 * @internal
 */
final class PostedInput
{
    public static function apply(Settings $settings): void
    {
        if ([] !== $settings->sizeSetRows) {
            $settings->sizeSets = SizeSetRows::toConfigs($settings->sizeSetRows, $settings->sizeSets);
        }

        if (array_key_exists('__submitted', $settings->alternateRows)) {
            $settings->alternates = AlternateRows::toConfigs($settings->alternateRows);
        }

        if (array_key_exists('__submitted', $settings->fieldAssignmentInput)) {
            $settings->fieldAssignments = AssignmentConfig::toArray(
                AssignmentConfig::fromInput($settings->fieldAssignmentInput),
            );
        }

        $settings->formatQuality = ConfigValues::postedIntegers($settings->formatQuality);
    }
}
