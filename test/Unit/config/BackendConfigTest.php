<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\config;

use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\config\ConfigValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BackendConfig::class)]
#[CoversClass(RemoteSettings::class)]
#[CoversClass(InvalidBackendException::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(ConfigValues::class)]
#[Group('unit')]
final class BackendConfigTest extends TestCase
{
    #[Test]
    public function defaultsToAutoWithNoOverrides(): void
    {
        static::assertEquals(new BackendChoice(), BackendConfig::choice('not an array'));
    }

    #[Test]
    public function readsBlankRemoteSettingsFromAnythingElse(): void
    {
        static::assertEquals(new RemoteSettings(), BackendConfig::remote(null));
    }

    #[Test]
    public function readsRemoteSettings(): void
    {
        $remote = BackendConfig::remote([
            'imgproxyUrl'       => ' https://img.example.test ',
            'imgproxyKey'       => '$IMGPROXY_KEY',
            'imgproxySalt'      => '$IMGPROXY_SALT',
            'cloudflareZoneUrl' => 'https://www.example.test',
        ]);

        static::assertEquals(
            new RemoteSettings(
                'https://img.example.test',
                '$IMGPROXY_KEY',
                '$IMGPROXY_SALT',
                'https://www.example.test',
            ),
            $remote,
        );
    }

    #[Test]
    public function readsTheChoiceSkippingBlankAndUnkeyedVolumes(): void
    {
        $choice = BackendConfig::choice([
            'default' => 'gd',
            'volumes' => ['volume-1' => 'imgproxy', 'volume-2' => '', 7 => 'imagick'],
        ]);

        static::assertEquals(new BackendChoice(BackendKind::Gd, ['volume-1' => BackendKind::Imgproxy]), $choice);
    }

    #[Test]
    public function rejectsAnUnknownBackend(): void
    {
        $this->expectException(InvalidBackendException::class);
        $this->expectExceptionMessage('“vips” isn\'t a backend.');

        BackendConfig::choice(['default' => 'vips']);
    }
}
