<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\delivery\VariantStoreInterface;
use parallelogram\images\picture\StoredVariantLinks;

/**
 * The variant filesystem, and how pages link to the files on it.
 *
 * @internal
 */
final readonly class VariantStorage
{
    public function __construct(
        public VariantStoreInterface $store,
        public StoredVariantLinks $links,
    ) {}
}
