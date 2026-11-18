<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use parallelogram\images\sizing\Variant;

/**
 * One variant of one image, with its hash and its path on the variant
 * filesystem.
 *
 * @api
 */
final readonly class VariantFile
{
    public function __construct(
        public Variant $variant,
        public string $hash,
        public string $path,
    ) {}
}
