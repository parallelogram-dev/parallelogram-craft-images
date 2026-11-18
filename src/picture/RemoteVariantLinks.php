<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use Override;
use parallelogram\images\remote\VariantUrlServiceInterface;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

/**
 * Links straight to an image service (imgproxy or Cloudflare Images) for a
 * volume that uses one.
 *
 * @api
 */
final readonly class RemoteVariantLinks implements VariantLinksInterface
{
    public function __construct(
        private VariantUrlServiceInterface $service,
        private SourceImage $source,
        private string $sourceUrl,
    ) {}

    #[Override]
    public function urlFor(VariantFile $file): string
    {
        return $this->service->urlFor($this->source, $file->variant, $this->sourceUrl);
    }
}
