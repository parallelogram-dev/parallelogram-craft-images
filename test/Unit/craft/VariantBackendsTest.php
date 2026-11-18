<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\craft\VariantBackends;
use parallelogram\images\remote\CloudflareUrls;
use parallelogram\images\remote\ImgproxySigner;
use parallelogram\images\remote\ImgproxyUrls;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariantBackends::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(CloudflareUrls::class)]
#[UsesClass(ImgproxySigner::class)]
#[UsesClass(ImgproxyUrls::class)]
#[UsesClass(Processing::class)]
#[UsesClass(RemoteSettings::class)]
#[Group('unit')]
final class VariantBackendsTest extends TestCase
{
    private static function backends(string $imgproxyKey = 'abcd'): VariantBackends
    {
        return new VariantBackends(
            new BackendChoice(BackendKind::Auto, [
                'proxied' => BackendKind::Imgproxy,
                'flared'  => BackendKind::Cloudflare,
            ]),
            new Processing(),
            new RemoteSettings('https://img.example.test', $imgproxyKey, 'ef01', 'https://www.example.test'),
        );
    }

    #[Test]
    public function linksCloudflareVolumesToCloudflare(): void
    {
        static::assertInstanceOf(CloudflareUrls::class, self::backends()->serviceFor('flared'));
    }

    #[Test]
    public function linksImgproxyVolumesToImgproxy(): void
    {
        static::assertInstanceOf(ImgproxyUrls::class, self::backends()->serviceFor('proxied'));
    }

    #[Test]
    public function refusesAnImgproxyKeyThatIsntHex(): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('The imgproxy key must be hex');

        self::backends(imgproxyKey: 'not-hex')->serviceFor('proxied');
    }

    #[Test]
    public function storesVariantsForOtherVolumes(): void
    {
        static::assertNull(self::backends()->serviceFor('local'));
    }
}
