<?php

namespace parallelogram\imagevariants\controllers;

use Craft;
use craft\web\Controller;
use parallelogram\imagevariants\jobs\WarmJob;
use yii\web\Response;

/**
 * Enqueues a WarmJob from the CP utility.
 */
class WarmController extends Controller
{
    public function actionQueue(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:image-variants-warm');

        $field = $this->request->getBodyParam('field') ?: null;
        $matrix = $this->request->getBodyParam('matrix') ?: null;
        $volume = $this->request->getBodyParam('volume') ?: null;

        Craft::$app->getQueue()->push(new WarmJob([
            'field' => $field,
            'matrix' => $matrix,
            'volume' => $volume,
        ]));

        $this->setSuccessFlash(Craft::t('image-variants', 'Warming job queued.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Active warm scopes, for the utility's live button state.
     */
    public function actionStatus(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('utility:image-variants-warm');

        return $this->asJson([
            'active' => array_values(Plugin::getInstance()->warmer->activeScopes()),
            'now' => time(),
        ]);
    }

    /**
     * Ask any in-flight warm (CLI or queue) to stop at its next tick.
     */
    public function actionStop(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:image-variants-warm');

        $warmer = Plugin::getInstance()->warmer;
        $warmer->requestStop();
        $cleared = $warmer->clearPendingJobs();

        $this->setSuccessFlash(Craft::t('image-variants', 'Stopping — the running warm will halt and {n} pending job(s) were cleared.', [
            'n' => $cleared,
        ]));

        return $this->redirectToPostedUrl();
    }
}
