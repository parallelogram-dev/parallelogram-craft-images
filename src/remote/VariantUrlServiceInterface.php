<?php

declare(strict_types=1);

namespace parallelogram\images\remote;

use parallelogram\images\sizing\Variant;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;

/**
 * An image service that makes variants on request from a URL: imgproxy or
 * Cloudflare Images. Volumes using one skip local storage and warming; pages
 * link to the service directly.
 *
 * @api
 */
interface VariantUrlServiceInterface
{
    /**
     * The URL of one variant of a source image.
     *
     * @param string $sourceUrl Where the service can fetch the original.
     *
     * @throws InvalidVariantException When the service can't make this variant.
     */
    public function urlFor(SourceImage $source, Variant $variant, string $sourceUrl): string;
}
