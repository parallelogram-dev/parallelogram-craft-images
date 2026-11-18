<?php

declare(strict_types=1);

namespace parallelogram\images\backends;

/**
 * Connection details for the remote image services, as stored: URLs may be
 * literal or environment references; the imgproxy key and salt are always
 * environment references.
 *
 * @api
 */
final readonly class RemoteSettings
{
    public function __construct(
        public string $imgproxyUrl = '',
        public string $imgproxyKey = '',
        public string $imgproxySalt = '',
        public string $cloudflareZoneUrl = '',
    ) {}
}
