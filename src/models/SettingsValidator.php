<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\config\AlternateConfig;
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

        $alternateErrors = self::alternateErrors($settings);

        if ([] !== $alternateErrors) {
            $errors['alternates'] = $alternateErrors;
        }

        $backendErrors = BackendChecks::errors($settings->backends);

        if ([] !== $backendErrors) {
            $errors['backends'] = $backendErrors;
        }

        foreach (SingleValueChecks::errors($settings) as $attribute => $message) {
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
        $settings->alternates       = AlternateConfig::toArray($settings->alternateImages());
        $settings->fieldAssignments = AssignmentConfig::toArray(
            AssignmentConfig::fromArray($settings->fieldAssignments)->prunedTo($catalogue),
        );
    }

    /**
     * @return list<string>
     */
    private static function alternateErrors(Settings $settings): array
    {
        try {
            $alternates = $settings->alternateImages();
        } catch (InvalidSizeSetException $exception) {
            return [$exception->getMessage()];
        }

        try {
            return $alternates->problemsWith($settings->catalogue());
        } catch (InvalidSizeSetException) {
            return [];
        }
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
