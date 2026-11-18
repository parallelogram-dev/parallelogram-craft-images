<?php

declare(strict_types=1);

namespace parallelogram\images;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\fields\Assets;
use craft\helpers\App;
use craft\web\UrlManager;
use craft\web\View;
use Imagick;
use Override;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\craft\AssetMethods;
use parallelogram\images\craft\AssetPictures;
use parallelogram\images\craft\BlitzIntegration;
use parallelogram\images\craft\ControlPanelEvents;
use parallelogram\images\craft\DeliveryFactory;
use parallelogram\images\craft\LiveCraftEnvironment;
use parallelogram\images\craft\PictureServiceFactory;
use parallelogram\images\craft\PlaceholderEvents;
use parallelogram\images\craft\SecretProvisioner;
use parallelogram\images\craft\Warming;
use parallelogram\images\craft\WarmingEvents;
use parallelogram\images\craft\WarmingFactory;
use parallelogram\images\delivery\VariantRequestHandler;
use parallelogram\images\events\PlaceholderSavedEvent;
use parallelogram\images\models\Settings;
use parallelogram\images\models\SettingsView;
use parallelogram\images\sizing\Format;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\Exception;

use function array_filter;
use function array_values;
use function extension_loaded;
use function is_string;
use function preg_quote;
use function trim;

/**
 * Parallelogram Images: responsive image variants made from size sets,
 * stored at stable paths, and generated on first request when missing.
 *
 * @api
 *
 * @method Settings getSettings()
 */
final class Plugin extends BasePlugin
{
    /**
     * Fired with a {@see PlaceholderSavedEvent} after an image's placeholder
     * colour is stored. Listen with
     * `Event::on(Plugin::class, Plugin::EVENT_AFTER_SAVE_PLACEHOLDER, ...)`.
     */
    public const string EVENT_AFTER_SAVE_PLACEHOLDER = 'afterSavePlaceholder';

    public bool $hasCpSettings = true;

    public string $schemaVersion = '0.1.0';

    private ?AssetPictures $assetPictures = null;

    private ?VariantRequestHandler $deliveryHandler = null;

    private ?Warming $warmingService = null;

    /**
     * Sends requests under the variant prefix to the plugin, once variants
     * are configured, passing the whole path (prefix included) as
     * `variantPath`. Files that exist are served by the web server first.
     */
    public static function registerVariantRoute(RegisterUrlRulesEvent $event): void
    {
        $settings = self::getInstance()?->getSettings();
        $prefix   = trim(self::parsed($settings->variantPrefix ?? ''), characters: '/');

        if (null === $settings || ! $settings->isConfigured() || '' === $prefix) {
            return;
        }

        $event->rules['<variantPath:' . preg_quote($prefix, delimiter: '#') . '/.+>'] = 'p-images/variants/serve';
    }

    private static function parsed(string $value): string
    {
        $parsed = App::parseEnv($value);

        return is_string($parsed) ? $parsed : '';
    }

    /**
     * The handler for variant requests, or null until variants are fully
     * configured.
     */
    public function delivery(): ?VariantRequestHandler
    {
        return $this->deliveryHandler ??= (new DeliveryFactory(new LiveCraftEnvironment()))->handler(
            $this->getSettings(),
        );
    }

    #[Override]
    public function init(): void
    {
        parent::init();

        $threads = $this->getSettings()->threadLimit;

        if ($threads > 0 && extension_loaded('imagick')) {
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, $threads);
        }

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, [self::class, 'registerVariantRoute']);

        AssetMethods::register(fn(): AssetPictures => (self::getInstance() ?? $this)->pictures());
        BlitzIntegration::register();
        PlaceholderEvents::register();
        ControlPanelEvents::register();
        WarmingEvents::register();
    }

    /**
     * What `asset.picture()` and `asset.variants()` use, built from the
     * current settings.
     */
    public function pictures(): AssetPictures
    {
        $settings = $this->getSettings();

        return $this->assetPictures ??= (new PictureServiceFactory(
            new LiveCraftEnvironment(),
            Craft::$app->getConfig()->getGeneral()->devMode,
            static function (string $message): void {
                Craft::warning($message, category: 'p-images');
            },
        ))->pictures($settings);
    }

    /**
     * Applies new settings and drops the request handler built from the old
     * ones.
     *
     * @param array<array-key, mixed> $settings
     */
    #[Override]
    public function setSettings(array $settings): void
    {
        parent::setSettings($settings);
        $this->assetPictures   = null;
        $this->deliveryHandler = null;
        $this->warmingService  = null;
    }

    /**
     * Making and removing variants ahead of time, or null until variants are
     * fully configured.
     */
    public function warming(): ?Warming
    {
        return $this->warmingService ??= (new WarmingFactory(new LiveCraftEnvironment()))->warming(
            $this->getSettings(),
        );
    }

    /**
     * Adds a variant secret to `.env` unless the site already has one.
     *
     * @throws Exception When the secret can't be generated or `.env` can't be written.
     */
    #[Override]
    protected function afterInstall(): void
    {
        parent::afterInstall();

        (new SecretProvisioner(
            App::env(...),
            /**
             * @throws Exception
             */
            static function (string $name, string $value): void {
                Craft::$app->getConfig()->setDotEnvVar($name, $value);
            },
            /**
             * @throws Exception
             */
            static fn(): string => Craft::$app->getSecurity()->generateRandomString(48),
        ))->provision();
    }

    #[Override]
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError When the settings template is broken.
     */
    #[Override]
    protected function settingsHtml(): ?string
    {
        $settings = $this->getSettings();
        /** @var View $view Craft's view is always its web view, in console requests too. */
        $view = Craft::$app->getView();

        return $view->renderTemplate('p-images/_settings', [
            'settings'    => $settings,
            'view'        => new SettingsView($settings),
            'assetFields' => array_values(array_filter(
                Craft::$app->getFields()->getAllFields(),
                static fn(object $field): bool => $field instanceof Assets,
            )),
            'formats'     => Format::cases(),
            'backends'    => BackendKind::cases(),
            'volumes'     => Craft::$app->getVolumes()->getAllVolumes(),
            'filesystems' => Craft::$app->getFs()->getAllFilesystems(),
        ]);
    }
}
