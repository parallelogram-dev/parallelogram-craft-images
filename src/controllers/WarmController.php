<?php

namespace parallelogram\images\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use parallelogram\images\jobs\WarmJob;
use parallelogram\images\Plugin;
use yii\web\Response;

/**
 * Enqueues a WarmJob from the CP utility.
 */
class WarmController extends Controller
{
    /**
     * Run a coverage audit synchronously (read-only — no generation, no queue),
     * so it works without a queue worker and returns a result immediately.
     */
    public function actionAudit(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:p-images-warm');

        App::maxPowerCaptain();

        $report = Plugin::getInstance()->warmer->audit(
            $this->request->getBodyParam('field') ?: null,
            $this->request->getBodyParam('matrix') ?: null,
        );

        $missing = array_sum(array_map(static fn(array $row) => $row['missing'], $report));

        $this->setSuccessFlash(
            0 === $missing
                ? Craft::t('p-images', 'Audit complete — all variants present.')
                : Craft::t('p-images', 'Audit complete — {n} transform(s) missing.', ['n' => $missing]),
        );

        return $this->redirectToPostedUrl();
    }

    public function actionQueue(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:p-images-warm');

        $field  = $this->request->getBodyParam('field') ?: null;
        $matrix = $this->request->getBodyParam('matrix') ?: null;
        $volume = $this->request->getBodyParam('volume') ?: null;

        Craft::$app->getQueue()
            ->push(new WarmJob([
                'field'  => $field,
                'matrix' => $matrix,
                'volume' => $volume,
            ]));

        $this->setSuccessFlash(Craft::t('p-images', 'Rebuilding job queued.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Active warm scopes, for the utility's live button state.
     */
    public function actionStatus(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePermission('utility:p-images-warm');

        $warmer = Plugin::getInstance()->warmer;
        $audit  = $warmer->cachedAudit();

        return $this->asJson([
            'active'    => array_values($warmer->activeScopes()),
            'pending'   => $warmer->pendingJobCounts(),
            'now'       => time(),
            'coverage'  => $audit['report'],
            'auditedAt' => $audit['auditedAt'],
        ]);
    }

    /**
     * Ask any in-flight warm (CLI or queue) to stop at its next tick.
     */
    public function actionStop(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:p-images-warm');

        $warmer = Plugin::getInstance()->warmer;
        $warmer->requestStop();
        $cleared = $warmer->clearPendingJobs();

        $this->setSuccessFlash(Craft::t(
            'p-images',
            'Stopping — the running rebuild will halt and {n} pending job(s) were cleared.',
            [
                'n' => $cleared,
            ],
        ));

        return $this->redirectToPostedUrl();
    }
}
