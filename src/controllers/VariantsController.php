<?php

declare(strict_types=1);

namespace parallelogram\images\controllers;

use craft\web\Controller;
use parallelogram\images\craft\DeliveryResponder;
use parallelogram\images\delivery\Delivery;
use parallelogram\images\Plugin;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Answers requests for variant files the web server couldn't find.
 *
 * @internal
 */
final class VariantsController extends Controller
{
    protected array|bool|int $allowAnonymous = ['serve' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE];

    public function actionServe(): Response
    {
        $handler = Plugin::getInstance()?->delivery();

        try {
            $delivery = null === $handler ? Delivery::notFound() : $handler->handle($this->request->getPathInfo());
        } catch (InvalidConfigException) {
            $delivery = Delivery::notFound();
        }

        return DeliveryResponder::apply($delivery, $this->response);
    }
}
