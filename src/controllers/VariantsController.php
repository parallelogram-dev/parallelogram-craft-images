<?php

declare(strict_types=1);

namespace parallelogram\images\controllers;

use craft\web\Controller;
use parallelogram\images\craft\DeliveryResponder;
use parallelogram\images\delivery\Delivery;
use parallelogram\images\Plugin;
use yii\web\Response;

/**
 * Answers requests for variant files the web server couldn't find.
 *
 * @internal
 */
final class VariantsController extends Controller
{
    protected array|bool|int $allowAnonymous = ['serve' => self::ALLOW_ANONYMOUS_LIVE | self::ALLOW_ANONYMOUS_OFFLINE];

    /**
     * @param string $variantPath The requested path, prefix included, as captured by the variant route.
     */
    public function actionServe(string $variantPath): Response
    {
        $handler = Plugin::getInstance()?->delivery();

        return DeliveryResponder::apply(
            null === $handler ? Delivery::notFound() : $handler->handle($variantPath),
            $this->response,
        );
    }
}
