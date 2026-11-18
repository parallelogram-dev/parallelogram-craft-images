<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\RemoteSettings;

use function array_combine;
use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_string;

/**
 * Reads the `backends` setting:
 *
 * ```php
 * [
 *     'default'           => 'auto',
 *     'volumes'           => ['<volume uid>' => 'imgproxy'],
 *     'imgproxyUrl'       => 'https://img.example.com',
 *     'imgproxyKey'       => '$IMGPROXY_KEY',
 *     'imgproxySalt'      => '$IMGPROXY_SALT',
 *     'cloudflareZoneUrl' => 'https://www.example.com',
 * ]
 * ```
 *
 * @internal
 */
final class BackendConfig
{
    /**
     * @throws InvalidBackendException When a backend name is unknown.
     */
    public static function choice(mixed $config): BackendChoice
    {
        $config  = is_array($config) ? $config : [];
        $default = ConfigValues::string($config['default'] ?? null);
        $volumes = [];
        $byUid   = is_array($config['volumes'] ?? null) ? $config['volumes'] : [];

        $kinds = array_combine(array_keys($byUid), array_map(ConfigValues::string(...), array_values($byUid)));

        foreach ($kinds as $volumeUid => $kind) {
            if (! is_string($volumeUid) || '' === $kind) {
                continue;
            }

            $volumes[$volumeUid] = self::kind($kind);
        }

        return new BackendChoice('' === $default ? BackendKind::Auto : self::kind($default), $volumes);
    }

    public static function remote(mixed $config): RemoteSettings
    {
        $config = is_array($config) ? $config : [];

        return new RemoteSettings(
            ConfigValues::string($config['imgproxyUrl'] ?? null),
            ConfigValues::string($config['imgproxyKey'] ?? null),
            ConfigValues::string($config['imgproxySalt'] ?? null),
            ConfigValues::string($config['cloudflareZoneUrl'] ?? null),
        );
    }

    /**
     * @throws InvalidBackendException
     */
    private static function kind(string $value): BackendKind
    {
        return (
            BackendKind::tryFrom($value)
                ?? throw new InvalidBackendException(
                    "“{$value}” isn't a backend. Use auto, vips, imagick, gd, imgproxy or cloudflare.",
                )
        );
    }
}
