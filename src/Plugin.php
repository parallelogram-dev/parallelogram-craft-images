<?php

namespace parallelogram\imagevariants;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use parallelogram\imagevariants\models\Settings;

/**
 * Parallelogram Image Variants.
 *
 * Owns the definition, generation, and URL production of responsive image
 * variants. Phase 1 establishes the package, settings model, and Project Config
 * wiring only; the Twig API, generation, CP screens, and console command arrive
 * in later phases.
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
        return Craft::$app->getView()->renderTemplate('image-variants/_settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }
}
