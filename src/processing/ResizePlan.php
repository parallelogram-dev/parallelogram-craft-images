<?php

declare(strict_types=1);

namespace parallelogram\images\processing;

use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Mode;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;

use function max;
use function min;
use function round;

/**
 * The steps that turn a source image into one variant, in order: an optional
 * crop, a resize, then an optional canvas the result is centred on.
 *
 * Working this out apart from any image library means every backend crops
 * and fits exactly the same way, and it can be tested without one.
 *
 * - No height: scale to the width, keeping proportions.
 * - Crop: cut the box's shape around the focal point, then scale to the box.
 * - Fit: scale to fit inside the box, keeping proportions.
 * - Letterbox: as Fit, then pad to the box.
 * - Stretch: scale to the box exactly.
 *
 * Nothing is scaled beyond the source's own size.
 *
 * @api
 */
final readonly class ResizePlan
{
    public function __construct(
        public ?Rect $crop,
        public Dimensions $resize,
        public ?Dimensions $canvas = null,
    ) {}

    public static function for(Dimensions $source, FocalPoint $focalPoint, Box $box): self
    {
        if (null === $box->height) {
            return self::proportional($source, $box->width);
        }

        if (Mode::Crop === $box->mode) {
            return self::cropped($source, $focalPoint, $box->width, $box->height);
        }

        if (Mode::Stretch === $box->mode) {
            return new self(null, new Dimensions($box->width, $box->height));
        }

        $fitted = self::fitted($source, $box->width, $box->height);

        return new self(
            null,
            $fitted,
            Mode::Letterbox === $box->mode ? new Dimensions($box->width, $box->height) : null,
        );
    }

    /**
     * Where a span of the given length starts when centred on a point, kept
     * within the image.
     */
    private static function centredOn(float $point, int $span, int $limit): int
    {
        return (int) max(0, min($limit - $span, round($point - ($span / 2))));
    }

    /**
     * Cuts the box's shape from the source, centred on the focal point but
     * kept inside the image, then scales it to the box.
     */
    private static function cropped(Dimensions $source, FocalPoint $focalPoint, int $width, int $height): self
    {
        $cropWidth  = min($source->width, self::scaled($source->height, $width, $height));
        $cropHeight = min($source->height, self::scaled($source->width, $height, $width));

        $x = self::centredOn($focalPoint->x * $source->width, $cropWidth, $source->width);
        $y = self::centredOn($focalPoint->y * $source->height, $cropHeight, $source->height);

        return new self(new Rect($x, $y, $cropWidth, $cropHeight), new Dimensions($width, $height));
    }

    private static function fitted(Dimensions $source, int $width, int $height): Dimensions
    {
        $scale = min($width / $source->width, $height / $source->height, 1.0);

        return new Dimensions(
            max(1, (int) round($source->width * $scale)),
            max(1, (int) round($source->height * $scale)),
        );
    }

    /**
     * Scales to a width, keeping proportions.
     */
    private static function proportional(Dimensions $source, int $width): self
    {
        $width = min($width, $source->width);

        return new self(null, new Dimensions($width, self::scaled($source->height, $width, $source->width)));
    }

    /**
     * A length scaled by a ratio, rounded, and never below one pixel.
     */
    private static function scaled(int $length, int $numerator, int $denominator): int
    {
        return max(1, (int) round(($length * $numerator) / $denominator));
    }
}
