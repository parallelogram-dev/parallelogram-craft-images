<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use function array_values;

/**
 * The formats to write, in preference order, and any quality values that
 * differ from the site's.
 *
 * @api
 */
final readonly class Encoding
{
    /**
     * @var non-empty-list<Format> Unique, in the order given.
     */
    public array $formats;

    public Format $fallback;

    public function __construct(
        public QualityTable $quality,
        Format $format,
        Format ...$moreFormats,
    ) {
        $byValue  = [$format->value => $format];
        $fallback = $format->isUniversal() ? $format : null;
        $last     = $format;

        foreach ($moreFormats as $more) {
            $byValue[$more->value] = $more;
            $last                  = $more;
            $fallback              ??= $more->isUniversal() ? $more : null;
        }

        /** @var non-empty-list<Format> $formats */
        $formats = array_values($byValue);

        $this->formats  = $formats;
        $this->fallback = $fallback ?? $last;
    }
}
