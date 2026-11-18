<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use parallelogram\images\sizing\Format;

/**
 * The parts of a requested variant path, once it is known to be well formed.
 * Whether the hash belongs to a real variant is checked separately.
 *
 * @api
 */
final readonly class ParsedVariantPath
{
    public function __construct(
        public string $assetUid,
        public string $hash,
        public string $basename,
        public Format $format,
    ) {}
}
