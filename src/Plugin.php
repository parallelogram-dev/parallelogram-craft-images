<?php

namespace parallelogram\images;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineMenuItemsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\fields\Assets;
use craft\helpers\App;
use craft\services\Assets as AssetsService;
use craft\services\Elements;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use parallelogram\images\craft\SecretProvisioner;
use parallelogram\images\elements\actions\RebuildVariants;
use parallelogram\images\listeners\AssetEvents;
use parallelogram\images\models\Settings;
use parallelogram\images\models\SettingsView;
use parallelogram\images\services\Generator;
use parallelogram\images\services\Pipeline;
use parallelogram\images\services\Resolver;
use parallelogram\images\services\Warmer;
use parallelogram\images\sizing\Format;
use parallelogram\images\utilities\WarmUtility;
use parallelogram\images\web\twig\VariantsVariable;
use yii\base\Event;

/**
 * Parallelogram Images.
 *
 * Owns the definition, generation, and URL production of responsive image
 * variants. Generation is synchronous (on save/replace) and via the warm
 * console command — never the queue. Render emits pre-generated static paths
 * via craft.pImages.variantSet().
 *
 * @property-read Settings $settings
 * @property-read Generator $generator
 * @property-read Pipeline $pipeline
 * @property-read Resolver $resolver
 * @property-read Warmer $warmer
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '0.1.0';

    public bool $hasCpSettings = true;

    /**
     * Add a "Rebuild image variants" item to the asset edit screen's action
     * menu. For an image with assigned matrices it is live and queues a
     * single-asset RebuildJob; for an image with none it is shown greyed-out
     * (so the feature stays discoverable) with the reason. Non-images, and users
     * who cannot save the asset, get nothing.
     */
    public function handleAssetActionMenuItems(DefineMenuItemsEvent $event): void
    {
        $asset = $event->sender;

        if (
            ! $asset instanceof Asset
            || Asset::KIND_IMAGE !== $asset->kind
            || ! $asset->id
            || $asset->getIsRevision()
        ) {
            return;
        }

        if (! Craft::$app->getUser()->checkPermission('saveAssets:' . $asset->getVolume()->uid)) {
            return;
        }

        $id    = sprintf('iv-rebuild-%s', mt_rand());
        $label = Craft::t('p-images', 'Rebuild image variants');

        if ($this->warmer->matrixHandlesForAsset($asset) === []) {
            $event->items[] = [
                'id'          => $id,
                'icon'        => 'arrows-rotate',
                'label'       => $label,
                'description' => Craft::t('p-images', 'Not used in a field with image variants.'),
                'disabled'    => true,
                'attributes'  => ['disabled' => true],
            ];

            return;
        }

        $event->items[] = [
            'id'    => $id,
            'icon'  => 'arrows-rotate',
            'label' => $label,
        ];

        $view = Craft::$app->getView();
        $view->registerJsWithVars(
            fn($id, $assetId) => <<<JS
                $('#' + $id).on('activate', () => {
                  Craft.sendActionRequest('POST', 'p-images/rebuild/queue', { data: { assetId: $assetId } })
                    .then((response) => Craft.cp.displayNotice(response.data.message))
                    .catch((e) => Craft.cp.displayError(e?.response?.data?.message));
                });
                JS,
            [
                $view->namespaceInputId($id),
                $asset->id,
            ],
        );
    }

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'generator' => Generator::class,
            'pipeline'  => Pipeline::class,
            'resolver'  => Resolver::class,
            'warmer'    => Warmer::class,
        ]);

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            static function (RegisterComponentTypesEvent $event): void {
                $event->types[] = WarmUtility::class;
            },
        );

        Event::on(
            Asset::class,
            Element::EVENT_REGISTER_ACTIONS,
            static function (RegisterElementActionsEvent $event): void {
                $event->actions[] = RebuildVariants::class;
            },
        );

        Event::on(
            Asset::class,
            Element::EVENT_DEFINE_ACTION_MENU_ITEMS,
            [$this, 'handleAssetActionMenuItems'],
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            [AssetEvents::class, 'handleElementSave'],
        );

        Event::on(
            AssetsService::class,
            AssetsService::EVENT_AFTER_REPLACE_ASSET,
            [AssetEvents::class, 'handleAssetReplace'],
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('pImages', VariantsVariable::class);
            },
        );
    }

    /**
     * Adds a variant secret to `.env` unless the site already has one.
     */
    protected function afterInstall(): void
    {
        parent::afterInstall();

        (new SecretProvisioner(
            static fn(string $name): ?string => is_string(App::env($name)) ? App::env($name) : null,
            static fn(string $name, string $value) => Craft::$app->getConfig()->setDotEnvVar($name, $value),
            static fn(): string => Craft::$app->getSecurity()->generateRandomString(48),
        ))->provision();
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
            static fn($field) => $field instanceof Assets,
        ));

        return Craft::$app->getView()
            ->renderTemplate('p-images/_settings', [
                'plugin'      => $this,
                'settings'    => $settings,
                'view'        => new SettingsView($settings),
                'assetFields' => $assetFields,
                'formats'     => Format::cases(),
            ]);
    }
}
