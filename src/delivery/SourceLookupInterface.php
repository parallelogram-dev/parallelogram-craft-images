<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

use parallelogram\images\variants\SourceImage;

/**
 * Finds the original image an asset UID refers to.
 *
 * @api
 */
interface SourceLookupInterface
{
    /**
     * Null when there is no such asset, or it isn't an image that can be resized.
     */
    public function find(string $assetUid): ?SourceImage;
}
