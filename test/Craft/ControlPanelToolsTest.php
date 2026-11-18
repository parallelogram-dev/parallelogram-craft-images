<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use craft\elements\Asset;
use craft\elements\db\EntryQuery;
use craft\elements\User;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\web\Request;
use craft\web\Response;
use craft\web\View;
use Override;
use parallelogram\images\controllers\UtilityController;
use parallelogram\images\craft\ControlPanelEvents;
use parallelogram\images\elements\actions\RebuildVariants;
use parallelogram\images\jobs\RebuildJob;
use parallelogram\images\jobs\SweepJob;
use parallelogram\images\jobs\WarmJob;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use parallelogram\images\test\Trait\CountsQueuedJobsTrait;
use parallelogram\images\test\Trait\UsesCraftAppTrait;
use parallelogram\images\utilities\WarmUtility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidValueException;
use yii\web\MethodNotAllowedHttpException;

use function str_contains;

/**
 * The queue jobs, the Utilities page and the Assets index action, against the
 * sandbox site.
 *
 * @internal
 */
#[CoversClass(ControlPanelEvents::class)]
#[CoversClass(RebuildJob::class)]
#[CoversClass(RebuildVariants::class)]
#[CoversClass(SweepJob::class)]
#[CoversClass(UtilityController::class)]
#[CoversClass(WarmJob::class)]
#[CoversClass(WarmUtility::class)]
#[Group('integration')]
#[Group('craft')]
final class ControlPanelToolsTest extends TestCase
{
    use CountsQueuedJobsTrait;
    use UsesCraftAppTrait;

    private bool $csrfValidation = true;

    private string $templateMode = View::TEMPLATE_MODE_SITE;

    private const string GALLERY_FIELD = '7abbc7e1-ad75-4a6c-b39d-ea23ffee2666';

    /**
     * @throws Throwable
     */
    #[Test]
    public function addsTheRebuildActionOnlyForPeopleWhoCanUseTheUtility(): void
    {
        $asAdmin = new RegisterElementActionsEvent(['actions' => []]);
        $asGuest = new RegisterElementActionsEvent(['actions' => []]);

        $this->signInAsAdmin();
        ControlPanelEvents::addRebuildAction($asAdmin);
        $this->signInAsGuest();
        ControlPanelEvents::addRebuildAction($asGuest);

        static::assertSame([[RebuildVariants::class], []], [$asAdmin->actions, $asGuest->actions]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function addsTheUtility(): void
    {
        $event = new RegisterComponentTypesEvent(['types' => []]);

        ControlPanelEvents::register();
        ControlPanelEvents::register();
        ControlPanelEvents::addUtility($event);

        static::assertSame([WarmUtility::class], $event->types);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function describesTheUtilityAndShowsItsButtons(): void
    {
        $html = WarmUtility::contentHtml();

        static::assertSame(
            ['Parallelogram Images', 'image', 'p-images-warm', true, true],
            [
                WarmUtility::displayName(),
                WarmUtility::icon(),
                WarmUtility::id(),
                str_contains($html, 'p-images/utility/warm'),
                str_contains($html, 'p-images/utility/sweep'),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function jobsDoNothingWhileVariantsAreOff(): void
    {
        $asset = $this->redImage();
        $this->plugin()->setSettings(['variantFilesystem' => '']);

        (new WarmJob())->execute(Craft::$app->getQueue());
        (new RebuildJob(['assetId' => $asset->id]))->execute(Craft::$app->getQueue());
        (new SweepJob())->execute(Craft::$app->getQueue());

        static::assertNull($this->plugin()->warming());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function jobsMakeRebuildAndSweepVariants(): void
    {
        $this->configureVariants([
            'fieldAssignments' => [
                ['fieldUid' => self::GALLERY_FIELD, 'layoutUid' => null, 'sizeSets' => ['card']],
            ],
        ]);
        $asset = $this->redImage();
        $fs    = Craft::$app->getFs()->getFilesystemByHandle('testVariants') ?? static::fail('No variants filesystem.');
        $stale = "asset/variant/{$asset->uid}/0123456789abcdef/harbour.avif";

        (new WarmJob(['volume' => 'testImages', 'sizeSet' => 'card']))->execute(Craft::$app->getQueue());
        $warmed = $this->plugin()->warming()?->missing($asset);
        $fs->write($stale, contents: 'old');
        (new RebuildJob(['assetId' => $asset->id]))->execute(Craft::$app->getQueue());
        $rebuilt = $fs->fileExists($stale);
        $fs->write($stale, contents: 'old');
        (new SweepJob())->execute(Craft::$app->getQueue());

        static::assertSame([[], false, false], [$warmed, $rebuilt, $fs->fileExists($stale)]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function queuesARebuildForEachSelectedImage(): void
    {
        $first  = $this->redImage();
        $second = $this->redImage();
        $before = self::queuedJobs('Rebuilding image variants');
        $action = new RebuildVariants();

        $handled = $action->performAction(Asset::find()->id([$first->id, $second->id]));

        static::assertSame(
            [true, $before + 2, 'Rebuilding variants of 2 images.', 'Rebuild image variants'],
            [
                $handled,
                self::queuedJobs('Rebuilding image variants'),
                $action->getMessage(),
                $action->getTriggerLabel(),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function queuesTheButtonsJobs(): void
    {
        $warm  = self::queuedJobs('Making image variants');
        $sweep = self::queuedJobs('Removing unused image variants');
        $this->signInAsAdmin();

        $responses = [$this->post('warm'), $this->post('sweep')];

        static::assertSame(
            [[302, 302], $warm + 1, $sweep + 1],
            [
                [$responses[0]->getStatusCode(), $responses[1]->getStatusCode()],
                self::queuedJobs('Making image variants'),
                self::queuedJobs('Removing unused image variants'),
            ],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function refusesButtonsThatArentPosted(): void
    {
        $this->signInAsAdmin();

        $this->expectException(MethodNotAllowedHttpException::class);

        Craft::$app->runAction('p-images/utility/warm');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function refusesToRebuildAnythingButAssets(): void
    {
        static::assertSame(
            [false, 'Delete and remake the variants of the selected images?'],
            [
                (new RebuildVariants())->performAction(new EntryQuery('craft\elements\Entry')),
                (new RebuildVariants())->getConfirmationMessage(),
            ],
        );
    }

    /**
     * Runs every test as a control panel request: control panel templates,
     * and no CSRF check on the simulated form posts.
     *
     * @throws Exception
     */
    #[Override]
    protected function setUp(): void
    {
        $this->setUpCraft();
        $request                       = $this->request();
        $this->csrfValidation          = $request->enableCsrfValidation;
        $request->enableCsrfValidation = false;
        $request->setIsCpRequest(true);
        $this->templateMode = $this->view()->getTemplateMode();
        $this->view()->setTemplateMode(View::TEMPLATE_MODE_CP);
    }

    /**
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidValueException
     */
    #[Override]
    protected function tearDown(): void
    {
        $request = $this->request();
        $request->getHeaders()->remove('X-Http-Method-Override');
        $request->setIsCpRequest(false);
        $request->enableCsrfValidation = $this->csrfValidation;
        $this->view()->setTemplateMode($this->templateMode);
        $this->signInAsGuest();
        $this->tearDownCraft();
    }

    /**
     * @throws Throwable
     */
    private function post(string $action): Response
    {
        $this->request()->getHeaders()->set('X-Http-Method-Override', value: 'POST');

        $response = Craft::$app->runAction("p-images/utility/{$action}");

        return $response instanceof Response ? $response : throw new Exception('No response.');
    }

    /**
     * @throws Throwable
     */
    private function redImage(): Asset
    {
        return $this->makeImageAsset(ImageFactory::solid(40, height: 20, colour: 'red', format: 'jpeg'));
    }

    private function request(): Request
    {
        $request = Craft::$app->getRequest();

        return $request instanceof Request ? $request : static::fail('The suite needs a web request.');
    }

    /**
     * @throws InvalidValueException
     */
    private function signInAsAdmin(): void
    {
        $user = User::find()->admin(true)->one();

        Craft::$app->getUser()->setIdentity($user instanceof User ? $user : null);
    }

    /**
     * @throws InvalidValueException
     */
    private function signInAsGuest(): void
    {
        Craft::$app->getUser()->setIdentity(null);
    }

    private function view(): View
    {
        $view = Craft::$app->getView();

        return $view instanceof View ? $view : static::fail('The suite needs a web view.');
    }
}
