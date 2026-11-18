<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use parallelogram\images\variants\Dimensions;

/**
 * Everything a template needs to show one image in one size set: each
 * format's `srcset` in preference order with the fallback format for `<img>`
 * last, the `sizes` value, the widest file's dimensions for `width` and
 * `height`, and the placeholder colour when the asset has one.
 *
 * This is what `asset.variants('handle')` returns.
 *
 * @api
 */
final readonly class PictureSources
{
    public FormatSource $fallback;

    /**
     * @param non-empty-list<FormatSource> $sources In preference order, ending with the fallback.
     */
    public function __construct(
        public string $sizeSet,
        public array $sources,
        public string $sizes,
        public Dimensions $size,
        public ?string $colour = null,
    ) {
        $fallback = $sources[0];

        foreach ($sources as $source) {
            $fallback = $source;
        }

        $this->fallback = $fallback;
    }

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
