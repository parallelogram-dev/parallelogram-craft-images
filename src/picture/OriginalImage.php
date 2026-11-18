<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

/**
 * The uploaded file itself, shown when there are no variants to use.
 *
 * @api
 */
final readonly class OriginalImage
{
    public function __construct(
        public string $url,
        public ?int $width,
        public ?int $height,
    ) {}
}
