<?php

namespace parallelogram\imagevariants;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\fields\Assets;
use craft\services\Assets as AssetsService;
use craft\services\Elements;
use craft\web\twig\variables\CraftVariable;
use parallelogram\imagevariants\listeners\AssetEvents;
use parallelogram\imagevariants\models\Settings;
use parallelogram\imagevariants\services\Generator;
use parallelogram\imagevariants\services\PathBuilder;
use parallelogram\imagevariants\services\Resolver;
use parallelogram\imagevariants\web\twig\VariantsVariable;
use yii\base\Event;

/**
 * Parallelogram Image Variants.
 *
 * Owns the definition, generation, and URL production of responsive image
 * variants. Generation is synchronous (on save/replace) and via the warm
 * console command — never the queue. Render emits pre-generated static paths
 * via craft.imageVariants.variantSet().
 *
 * @property-read Settings $settings
 * @property-read Generator $generator
 * @property-read PathBuilder $paths
 * @property-read Resolver $resolver
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '0.1.0';

    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'generator' => Generator::class,
            'paths' => PathBuilder::class,
            'resolver' => Resolver::class,
        ]);

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            [AssetEvents::class, 'handleElementSave']
        );

        Event::on(
            AssetsService::class,
            AssetsService::EVENT_AFTER_REPLACE_ASSET,
            [AssetEvents::class, 'handleAssetReplace']
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('imageVariants', VariantsVariable::class);
            }
        );
    }

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
