<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Override;
use parallelogram\images\remote\VariantUrlServiceInterface;
use parallelogram\images\sizing\Variant;
use parallelogram\images\variants\SourceImage;

/**
 * An image service whose URLs spell out what was asked for, so tests can
 * read them.
 *
 * @internal
 */
final class QueryStringUrlService implements VariantUrlServiceInterface
{
    #[Override]
    public function urlFor(SourceImage $source, Variant $variant, string $sourceUrl): string
    {
        return "{$sourceUrl}?asset={$source->uid}&w={$variant->box->width}&f={$variant->format->value}";
    }
}
