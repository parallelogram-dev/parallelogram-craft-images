<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\VariantFile;

/**
 * Where pages link to each variant of one image.
 *
 * @api
 */
interface VariantLinksInterface
{
    /**
     * @throws InvalidVariantException When the image service can't make this variant.
     */
    public function urlFor(VariantFile $file): string;
}
