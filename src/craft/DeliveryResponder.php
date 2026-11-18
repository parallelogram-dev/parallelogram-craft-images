<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\delivery\Delivery;
use parallelogram\images\delivery\DeliveryOutcome;
use yii\web\Response;

/**
 * Turns a delivery into an HTTP response.
 *
 * A variant's path changes whenever its pixels would, so files and redirects
 * can be cached for a year. "Not found" is cached briefly, so crawlers asking
 * for outdated paths don't reach PHP every time. "Unavailable" asks the
 * visitor to retry shortly and is never cached.
 *
 * @internal
 */
final class DeliveryResponder
{
    private const string FOREVER = 'public, max-age=31536000, immutable';

    public static function apply(Delivery $delivery, Response $response): Response
    {
        $headers           = $response->getHeaders();
        $response->format  = Response::FORMAT_RAW;
        $response->content = $delivery->body;

        if (DeliveryOutcome::Content === $delivery->outcome) {
            $headers->set('Cache-Control', value: self::FOREVER);
            $headers->set('Content-Type', value: $delivery->mimeType);

            return $response->setStatusCode(200);
        }

        if (DeliveryOutcome::Redirect === $delivery->outcome) {
            $headers->set('Cache-Control', value: self::FOREVER);
            $headers->set('Location', value: $delivery->location);

            return $response->setStatusCode(301);
        }

        if (DeliveryOutcome::NotFound === $delivery->outcome) {
            $headers->set('Cache-Control', value: 'public, max-age=300');

            return $response->setStatusCode(404);
        }

        $headers->set('Cache-Control', value: 'no-store');
        $headers->set('Retry-After', value: '5');

        return $response->setStatusCode(503);
    }
}
