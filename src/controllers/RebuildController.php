<?php

namespace parallelogram\imagevariants\controllers;

use Craft;
use craft\elements\Asset;
use craft\web\Controller;
use parallelogram\imagevariants\jobs\RebuildJob;
use yii\web\Response;

/**
 * Enqueues a single-asset RebuildJob from the asset edit screen's action menu.
 */
class RebuildController extends Controller
{
    public function actionQueue(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $assetId = (int)$this->request->getRequiredBodyParam('assetId');
        $asset = Asset::find()->id($assetId)->kind('image')->one();

        if (!$asset instanceof Asset) {
            return $this->asFailure(Craft::t('image-variants', 'Image not found.'));
        }

        $this->requirePermission('saveAssets:' . $asset->getVolume()->uid);

        Craft::$app->getQueue()->push(new RebuildJob(['assetId' => $assetId]));

        return $this->asSuccess(Craft::t('image-variants', 'Rebuild queued — variants will regenerate in the background.'));
    }
}
