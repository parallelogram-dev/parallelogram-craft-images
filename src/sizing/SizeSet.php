<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use function array_unique;
use function array_values;
use function preg_match;
use function sort;
use function trim;

/**
 * A named set of sizes an image can be shown at: a ladder of widths, the
 * `sizes` attribute that tells the browser which to pick, how images are
 * fitted, and which formats are written.
 *
 * @api
 */
final readonly class SizeSet
{
    private const int MAX_WIDTH = 10_000;

    /**
     * @var non-empty-list<int> Ascending and unique.
     */
    public array $widths;

    public string $sizes;

    /**
     * @param list<int> $widths
     *
     * @throws InvalidSizeSetException When the handle, widths or sizes cannot be used.
     */
    public function __construct(
        public string $handle,
        array $widths,
        string $sizes,
        public Encoding $encoding,
        public Fit $fit = new Fit(),
    ) {
        if (1 !== preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $handle)) {
            throw new InvalidSizeSetException(
                "“{$handle}” is not a valid handle. Start with a letter, then use letters, numbers, dashes or underscores.",
            );
        }

        $this->widths = self::checkWidths($handle, $widths);
        $this->sizes  = trim($sizes);

        if ('' === $this->sizes) {
            throw new InvalidSizeSetException("“{$handle}” needs a sizes value, such as 100vw.");
        }
    }

    /**
     * @param list<int> $widths
     *
     * @return non-empty-list<int>
     *
     * @throws InvalidSizeSetException
     */
    private static function checkWidths(string $handle, array $widths): array
    {
        $unique = array_values(array_unique($widths));

        if ([] === $unique) {
            throw new InvalidSizeSetException("“{$handle}” needs at least one width.");
        }

        foreach ($unique as $width) {
            if ($width < 1 || $width > self::MAX_WIDTH) {
                throw new InvalidSizeSetException(
                    "“{$handle}” has a width of {$width}px. Widths must be from 1 to 10,000px.",
                );
            }
        }

        sort($unique);

        return $unique;
    }

    /**
     * Every file to generate for a source image of the given size, grouped by
     * format in preference order and ascending by width.
     *
     * @return list<Variant>
     */
    public function variantsFor(int $sourceWidth, int $sourceHeight, QualityTable $siteQuality): array
    {
        $quality  = $siteQuality->overriddenBy($this->encoding->quality);
        $widths   = $this->widthsFor($sourceWidth, $sourceHeight);
        $variants = [];

        foreach ($this->encoding->formats as $format) {
            foreach ($widths as $width) {
                $variants[] = new Variant($this->handle, $this->fit->boxFor($width), $format, $quality->for($format));
            }
        }

        return $variants;
    }

    /**
     * The ladder widths a source of the given size can fill. Images are never
     * upscaled: widths the source can't fill are dropped, and when none fit,
     * the widest box the source can fill is used on its own.
     *
     * @return non-empty-list<int>
     */
    public function widthsFor(int $sourceWidth, int $sourceHeight): array
    {
        $limit   = $this->fit->widestFor($sourceWidth, $sourceHeight);
        $fitting = [];

        foreach ($this->widths as $width) {
            if ($width > $limit) {
                break;
            }

            $fitting[] = $width;
        }

        return [] === $fitting ? [$limit] : $fitting;
    }
}
