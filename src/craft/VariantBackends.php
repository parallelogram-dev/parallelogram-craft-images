<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\remote\CloudflareUrls;
use parallelogram\images\remote\ImgproxySigner;
use parallelogram\images\remote\ImgproxyUrls;
use parallelogram\images\remote\VariantUrlServiceInterface;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;

/**
 * Which backend each volume uses, the processing settings they share, and
 * how to reach the remote image services.
 *
 * @internal
 */
final readonly class VariantBackends
{
    /**
     * @param RemoteSettings $remote With environment variables already resolved.
     */
    public function __construct(
        public BackendChoice $choice,
        public Processing $processing,
        private RemoteSettings $remote,
    ) {}

    /**
     * The image service a volume's pages link to, or null when its variants
     * are made and stored by the site.
     *
     * @throws InvalidVariantException When the imgproxy key or salt can't be used.
     */
    public function serviceFor(?string $volumeUid): ?VariantUrlServiceInterface
    {
        $kind = $this->choice->forVolume($volumeUid);

        if (BackendKind::Imgproxy === $kind) {
            return new ImgproxyUrls(
                $this->remote->imgproxyUrl,
                new ImgproxySigner($this->remote->imgproxyKey, $this->remote->imgproxySalt),
                $this->processing,
            );
        }

        return BackendKind::Cloudflare === $kind
            ? new CloudflareUrls($this->remote->cloudflareZoneUrl, $this->processing)
            : null;
    }
}
