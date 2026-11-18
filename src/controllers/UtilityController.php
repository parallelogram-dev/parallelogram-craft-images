<?php

declare(strict_types=1);

namespace parallelogram\images\controllers;

use Craft;
use craft\helpers\Queue;
use craft\web\Controller;
use parallelogram\images\jobs\SweepJob;
use parallelogram\images\jobs\WarmJob;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * The buttons on Utilities → Parallelogram Images. Each queues a job, whose
 * progress shows in Craft's own queue.
 *
 * @internal
 */
final class UtilityController extends Controller
{
    private const string PERMISSION = 'utility:p-images-warm';

    /**
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSweep(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission(self::PERMISSION);

        Queue::push(new SweepJob());
        $this->setSuccessFlash(Craft::t('p-images', message: 'Removing unused image variants.'));

        return $this->redirect('utilities/p-images-warm');
    }

    /**
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionWarm(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission(self::PERMISSION);

        Queue::push(new WarmJob());
        $this->setSuccessFlash(Craft::t('p-images', message: 'Making image variants.'));

        return $this->redirect('utilities/p-images-warm');
    }
}
