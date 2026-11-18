<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\config\BackendConfig;

use function str_starts_with;

/**
 * Checks the backend settings: known backend names, and complete connection
 * details for any remote service in use, with the imgproxy key and salt kept
 * in environment variables so they never reach project config.
 *
 * @internal
 */
final class BackendChecks
{
    /**
     * @return list<string>
     */
    public static function errors(mixed $config): array
    {
        try {
            $choice = BackendConfig::choice($config);
        } catch (InvalidBackendException $exception) {
            return [$exception->getMessage()];
        }

        $remote = BackendConfig::remote($config);
        $errors = [];

        if ($choice->uses(BackendKind::Imgproxy)) {
            if ('' === $remote->imgproxyUrl) {
                $errors[] = 'Enter the address of your imgproxy server.';
            }

            if (! str_starts_with($remote->imgproxyKey, '$') || ! str_starts_with($remote->imgproxySalt, '$')) {
                $errors[] = 'Set the imgproxy key and salt as environment variables, e.g. $IMGPROXY_KEY and $IMGPROXY_SALT, so they stay out of project config.';
            }
        }

        if ($choice->uses(BackendKind::Cloudflare) && '' === $remote->cloudflareZoneUrl) {
            $errors[] = 'Enter the address of the Cloudflare zone that serves your images.';
        }

        return $errors;
    }
}
