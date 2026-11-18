<?php

declare(strict_types=1);

namespace parallelogram\images\processing;

/**
 * What shows through where an image has no pixels: padding, or transparent
 * areas once flattened.
 *
 * @api
 */
enum Backdrop
{
    case Transparent;
    case White;

    /**
     * GD's alpha for this backdrop: 0 is opaque, 127 fully transparent.
     */
    public function alpha(): int
    {
        return self::White === $this ? 0 : 127;
    }
}
