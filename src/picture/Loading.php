<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

/**
 * How a picture's image loads.
 *
 * @api
 */
enum Loading
{
    /**
     * Real sources with `loading="lazy"`: the browser decides when.
     */
    case Lazy;

    /**
     * Sources in `data-lazysrc-*` attributes for ParallelogramJS's Lazysrc,
     * followed by a `<noscript>` copy with real ones.
     */
    case Lazysrc;

    /**
     * Real sources with `fetchpriority="high"`, for the largest image above
     * the fold.
     */
    case Priority;
}
