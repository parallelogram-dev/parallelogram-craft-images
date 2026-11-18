<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use parallelogram\images\craft\DeliveryResponder;
use parallelogram\images\delivery\Delivery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use yii\web\Response;

/**
 * @internal
 */
#[CoversClass(DeliveryResponder::class)]
#[UsesClass(Delivery::class)]
#[Group('unit')]
final class DeliveryResponderTest extends TestCase
{
    /**
     * @return array<string, array{Delivery, int, array<string, string>, string}>
     */
    public static function deliveryProvider(): array
    {
        $forever = 'public, max-age=31536000, immutable';

        return [
            'content'     => [
                Delivery::content('avif bytes', mimeType: 'image/avif'),
                200,
                ['cache-control' => $forever, 'content-type' => 'image/avif'],
                'avif bytes',
            ],
            'redirect'    => [
                Delivery::redirect('https://cdn.example.test/a.avif'),
                301,
                ['cache-control' => $forever, 'location' => 'https://cdn.example.test/a.avif'],
                '',
            ],
            'not found'   => [Delivery::notFound(), 404, ['cache-control' => 'public, max-age=300'], ''],
            'unavailable' => [Delivery::unavailable(), 503, ['cache-control' => 'no-store', 'retry-after' => '5'], ''],
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('deliveryProvider')]
    #[Test]
    public function setsStatusHeadersAndBody(Delivery $delivery, int $status, array $headers, string $body): void
    {
        $response = DeliveryResponder::apply($delivery, new Response(['charset' => 'UTF-8']));

        $actual = [];

        foreach ($response->getHeaders() as $name => $values) {
            $actual[$name] = $values[0] ?? '';
        }

        static::assertSame(
            [$status, $headers, $body, Response::FORMAT_RAW],
            [$response->getStatusCode(), $actual, $response->content, $response->format],
        );
    }
}
