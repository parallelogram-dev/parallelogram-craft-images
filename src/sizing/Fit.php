<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

/**
 * How images are fitted: the mode, and the aspect ratio the boxes share (none
 * keeps each source's own proportions).
 *
 * @api
 */
final readonly class Fit
{
    public function __construct(
        public Mode $mode = Mode::Crop,
        public ?AspectRatio $ratio = null,
    ) {}

    /**
     * The box for one width.
     */
    public function boxFor(int $width): Box
    {
        return new Box($width, $this->ratio?->heightFor($width), $this->mode);
    }

    /**
     * The widest box a source can fill without upscaling. Only a cropped ratio
     * can be limited by the source's height.
     */
    public function widestFor(int $sourceWidth, int $sourceHeight): int
    {
        if (null === $this->ratio || Mode::Crop !== $this->mode) {
            return $sourceWidth;
        }

        return $this->ratio->widestWithin($sourceWidth, $sourceHeight);
    }
}
