<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use parallelogram\images\sizing\Format;

/**
 * One format of an image at every width: its `srcset`, and the URL of its
 * widest file for places that take a single URL.
 *
 * @api
 */
final readonly class FormatSource
{
    public string $mimeType;

    public function __construct(
        public Format $format,
        public string $srcset,
        public string $url,
    ) {
        $this->mimeType = $format->mimeType();
    }
}
