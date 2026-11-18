<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Closure;
use Craft;
use craft\elements\User;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Config;
use craft\web\Request as WebRequest;
use craft\web\Response;
use craft\web\TemplateResponseBehavior;
use craft\web\UrlManager;
use craft\web\UrlRule;
use craft\web\View;
use Imagick;
use Override;
use parallelogram\images\delivery\VariantRequestHandler;
use parallelogram\images\Plugin;
use parallelogram\images\test\TestAsset\EnvironmentVariables;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Throwable;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\Exception;
use yii\web\Request;

use function array_key_first;
use function file_get_contents;
use function file_put_contents;
use function is_string;
use function str_contains;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * @internal
 */
#[CoversClass(Plugin::class)]
#[Group('integration')]
#[Group('craft')]
final class PluginTest extends TestCase
{
    use UsesCraftAppTrait;

    private static function settingsHtml(mixed $response): string
    {
        $template = $response instanceof Response ? $response->getBehavior(TemplateResponseBehavior::NAME) : null;

        return $template instanceof TemplateResponseBehavior
            ? self::text($template->variables['settingsHtml'] ?? '')
            : '';
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addsNoRouteWhenThePrefixVariableIsUnset(): void
    {
        $this->configureVariants(['variantPrefix' => '$P_IMAGES_TEST_UNSET_PREFIX']);
        $event = new RegisterUrlRulesEvent();

        Plugin::registerVariantRoute($event);

        static::assertSame([], $event->rules);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addsNoRouteWithoutAPluginInstance(): void
    {
        $original = Plugin::getInstance();
        $event    = new RegisterUrlRulesEvent();
        Plugin::setInstance(null);

        try {
            Plugin::registerVariantRoute($event);
        } finally {
            Plugin::setInstance($original);
        }

        static::assertSame([], $event->rules);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addsTheVariantRouteOnlyOnceConfigured(): void
    {
        $unconfigured = new RegisterUrlRulesEvent();
        Plugin::registerVariantRoute($unconfigured);

        $this->configureVariants();
        $configured = new RegisterUrlRulesEvent();
        Plugin::registerVariantRoute($configured);

        $pattern = (string) array_key_first($configured->rules);
        $rule    = new UrlRule(['pattern' => $pattern, 'route' => 'p-images/variants/serve']);
        $request = new Request(['pathInfo' => 'asset/variant/abc/def/harbour.avif']);

        static::assertSame(
            [[], ['p-images/variants/serve', ['variantPath' => 'asset/variant/abc/def/harbour.avif']]],
            [$unconfigured->rules, $rule->parseRequest(Craft::$app->getUrlManager(), $request)],
        );
    }

    /**
     * A fresh instance gets its saved settings before init(), as Craft gives
     * them, so init() applies the thread limit and registers the route. The
     * limit itself can't be asserted: ImageMagick builds without OpenMP fix it
     * at 1. The original instance, limit and route registration are restored.
     */
    #[Test]
    public function appliesTheThreadLimitOnInit(): void
    {
        $original = Plugin::getInstance();
        $before   = Imagick::getResourceLimit(Imagick::RESOURCETYPE_THREAD);
        $route    = [Plugin::class, 'registerVariantRoute'];

        try {
            $fresh  = new Plugin('p-images', Craft::$app, ['settings' => ['threadLimit' => 2]]);
            $routed = Event::hasHandlers(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES);
        } finally {
            Plugin::setInstance($original);
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, (int) $before);
            Event::off(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, $route);
            Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, $route);
        }

        static::assertSame([2, true], [$fresh->getSettings()->threadLimit, $routed]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function buildsTheRequestHandlerOnlyOnceConfigured(): void
    {
        $before = $this->plugin()->delivery();
        $this->configureVariants();

        static::assertSame([null, true], [$before, $this->plugin()->delivery() instanceof VariantRequestHandler]);
    }

    /**
     * With a secret already set, installing must not touch `.env`: replacing
     * the secret would change every variant URL.
     *
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function leavesAnExistingSecretAloneOnInstall(): void
    {
        static::assertSame("OTHER=1\n", $this->dotEnvAfterInstall(static fn(Closure $install): mixed => $install()));
    }

    /**
     * Renders the settings page the way the control panel does, as an admin,
     * and checks the plugin's own part of it (the full page would need a
     * session, which PHP can't start once PHPUnit has printed output).
     *
     * @throws Exception
     * @throws Throwable
     */
    #[Test]
    public function rendersTheSettingsScreen(): void
    {
        $admin = User::find()->admin(true)->one();
        Craft::$app->getUser()->setIdentity($admin instanceof User ? $admin : null);
        $request = Craft::$app->getRequest();
        $request instanceof WebRequest
            ? $request->setIsCpRequest(true)
            : static::fail('The suite needs a web request.');

        /** @var View $view Craft's view is always its web view. */
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        try {
            $response = Craft::$app->runAction('plugins/edit-plugin-settings', ['handle' => 'p-images']);
            $html     = self::settingsHtml($response);
        } finally {
            $view->setTemplateMode($mode);
            $request->setIsCpRequest(false);
            Craft::$app->getUser()->setIdentity(null);
        }

        static::assertSame([true, true], [str_contains($html, 'Size sets'), str_contains($html, 'Variant filesystem')]);
    }

    /**
     * Points Craft's `.env` at a temporary file for the duration (resetting
     * the path Craft memoises), so the sandbox's real `.env` is never touched.
     *
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function writesASecretOnInstallWhenTheSiteHasNone(): void
    {
        $written = $this->dotEnvAfterInstall(
            static fn(Closure $install): mixed => EnvironmentVariables::without('P_IMAGES_SECRET', $install),
        );

        static::assertMatchesRegularExpression('/^P_IMAGES_SECRET="?[A-Za-z0-9_-]{48}"?$/m', $written);
    }

    /**
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        $this->setUpCraft();
    }

    /**
     * @throws ErrorException
     * @throws Exception
     */
    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownCraft();
    }

    /**
     * Installs the plugin against a temporary `.env` holding only `OTHER=1`,
     * through the given runner (which may change the environment around it),
     * and returns that file afterwards.
     *
     * @param Closure(Closure(): void): mixed $runner
     *
     * @throws Exception
     * @throws ReflectionException
     */
    private function dotEnvAfterInstall(Closure $runner): string
    {
        $dotEnv = (string) tempnam(sys_get_temp_dir(), prefix: 'p-images-env-');
        $alias  = Craft::getAlias('@dotenv');
        $memo   = new ReflectionProperty(Config::class, '_dotEnvPath');
        file_put_contents($dotEnv, data: "OTHER=1\n");

        Craft::setAlias('@dotenv', $dotEnv);
        $memo->setValue(Craft::$app->getConfig(), value: null);

        try {
            $runner(
                /**
                 * @throws Exception
                 */
                function (): void {
                    $this->plugin()->install();
                },
            );

            return (string) file_get_contents($dotEnv);
        } finally {
            Craft::setAlias('@dotenv', $alias);
            $memo->setValue(Craft::$app->getConfig(), value: null);
            unlink($dotEnv);
        }
    }
}
