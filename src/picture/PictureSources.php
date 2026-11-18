<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use parallelogram\images\variants\Dimensions;

/**
 * Everything a template needs to show one image in one size set: each
 * format's `srcset` in preference order, the fallback format for `<img>`, the
 * `sizes` value and the widest file's dimensions for `width` and `height`.
 *
 * This is what `asset.variants('handle')` returns.
 *
 * @api
 */
final readonly class PictureSources
{
    /**
     * @param list<FormatSource> $sources In preference order, the fallback included.
     */
    public function __construct(
        public string $sizeSet,
        public array $sources,
        public FormatSource $fallback,
        public string $sizes,
        public Dimensions $size,
    ) {}

    /**
     * The `srcset` of one format, e.g. `variants.srcset('avif')`, or null when
     * the size set doesn't write it.
     */
    public function srcset(string $format): ?string
    {
        foreach ($this->sources as $source) {
            if ($source->format->value === $format) {
                return $source->srcset;
            }
        }

        return null;
    }
}
