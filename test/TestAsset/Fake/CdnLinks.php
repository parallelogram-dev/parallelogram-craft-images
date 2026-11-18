<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Override;
use parallelogram\images\picture\VariantLinksInterface;
use parallelogram\images\variants\VariantFile;

/**
 * Links every variant to a fixed CDN by its path.
 *
 * @internal
 */
final class CdnLinks implements VariantLinksInterface
{
    #[Override]
    public function urlFor(VariantFile $file): string
    {
        return "https://cdn.test/{$file->path}";
    }
}
