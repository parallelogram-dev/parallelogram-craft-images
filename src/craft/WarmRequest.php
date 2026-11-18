<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

/**
 * An image to make variants of, and which size sets to make.
 *
 * @internal
 */
final readonly class WarmRequest
{
    /**
     * @param list<string> $sizeSets Handles.
     */
    public function __construct(
        public int $assetId,
        public array $sizeSets,
    ) {}
}
