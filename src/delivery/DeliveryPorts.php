<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

/**
 * Everything the request handler needs from outside: assets, storage, image
 * processing and locking.
 *
 * @api
 */
final readonly class DeliveryPorts
{
    public function __construct(
        public SourceLookupInterface $sources,
        public VariantStoreInterface $store,
        public VariantGeneratorInterface $generator,
        public VariantLockInterface $lock,
    ) {}
}
