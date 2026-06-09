<?php

namespace parallelogram\imagevariants;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\fields\Assets;
use parallelogram\imagevariants\models\Settings;

/**
 * Parallelogram Image Variants.
 *
 * Owns the definition, generation, and URL production of responsive image
 * variants. Phases 1–3 establish the package, settings model, and the matrices +
 * field-assignment CP screens; the Twig API, generation, and console command
 * arrive in later phases.
 *
 * @property-read Settings $settings
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '0.1.0';

    public bool $hasCpSettings = true;

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        $settings = $this->getSettings();

        $assetFields = array_values(array_filter(
            Craft::$app->getFields()->getAllFields(),
            static fn($field) => $field instanceof Assets
        ));

        return Craft::$app->getView()->renderTemplate('image-variants/_settings', [
            'plugin' => $this,
            'settings' => $settings,
            'assetFields' => $assetFields,
            'matrixHandles' => array_keys($settings->matrices),
        ]);
    }
}
