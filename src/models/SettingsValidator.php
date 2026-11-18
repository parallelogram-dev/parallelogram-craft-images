<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\config\SizeSetTable;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;

use function implode;

/**
 * Checks plugin settings, and once they pass, stores size sets in their
 * normalised form (sorted widths, explicit formats and mode) and drops field
 * assignments to size sets that no longer exist.
 *
 * @internal
 */
final class SettingsValidator
{
    /**
     * Problems keyed by setting, in the order they should be shown.
     *
     * @return array<string, list<string>>
     */
    public static function errors(Settings $settings): array
    {
        $errors = [];

        try {
            $defaults = $settings->defaultFormatList();
        } catch (InvalidSizeSetException $exception) {
            $errors['defaultFormats'][] = $exception->getMessage();
            $defaults                   = null;
        }

        if ([] === $settings->defaultFormats) {
            $errors['defaultFormats'][] = 'Choose at least one default format.';
        }

        $sizeSetErrors = self::sizeSetErrors($settings, $defaults);

        if ([] !== $sizeSetErrors) {
            $errors['sizeSets'] = $sizeSetErrors;
        }

        foreach (self::otherErrors($settings) as $attribute => $message) {
            $errors[$attribute][] = $message;
        }

        return $errors;
    }

    /**
     * Rewrites valid settings in their normalised form. Only call this once
     * {@see errors()} returns nothing.
     *
     * @throws InvalidSizeSetException When the settings are not valid.
     */
    public static function normalise(Settings $settings): void
    {
        $catalogue = $settings->catalogue();
        $sizeSets  = [];

        foreach ($catalogue->all() as $sizeSet) {
            $sizeSets[$sizeSet->handle] = SizeSetConfig::toArray($sizeSet);
        }

        $settings->sizeSets         = $sizeSets;
        $settings->fieldAssignments = AssignmentConfig::toArray(
            AssignmentConfig::fromArray($settings->fieldAssignments)->prunedTo($catalogue),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function otherErrors(Settings $settings): array
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

        return $errors;
    }

    /**
     * @param non-empty-list<Format>|null $defaults Null when the default formats are invalid.
     *
     * @return list<string>
     */
    private static function sizeSetErrors(Settings $settings, ?array $defaults): array
    {
        $errors     = [];
        $duplicates = SizeSetTable::duplicateHandles($settings->sizeSetRows);

        if ([] !== $duplicates) {
            $errors[] = 'Each size set needs its own handle. Repeated: ' . implode(', ', $duplicates) . '.';
        }

        if (null === $defaults) {
            return $errors;
        }

        foreach ($settings->sizeSets as $handle => $config) {
            try {
                SizeSetConfig::fromArray($handle, $config, $defaults);
            } catch (InvalidSizeSetException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return $errors;
    }
}
