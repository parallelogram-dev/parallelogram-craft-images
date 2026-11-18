<?php

declare(strict_types=1);

namespace parallelogram\images\processing\gd;

use GdImage;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\processing\Backdrop;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\variants\Dimensions;

use function imagealphablending;
use function imagecolorallocatealpha;
use function imageconvolution;
use function imagecopy;
use function imagecopyresampled;
use function imagecreatetruecolor;
use function imagecrop;
use function imagefill;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function intdiv;

/**
 * Carries out a {@see ResizePlan} with GD: crop, resample, sharpen, then pad.
 *
 * @internal
 */
final class GdCanvas
{
    /**
     * A new image of the given size, filled with the backdrop.
     *
     * @throws GenerationFailedException
     */
    public static function blank(Dimensions $size, Backdrop $backdrop): GdImage
    {
        $image = GdGuard::call('create an image', static fn(): GdImage|false => imagecreatetruecolor(
            $size->width,
            $size->height,
        ));
        $fill = GdGuard::call('choose a colour', static fn(): int|false => imagecolorallocatealpha(
            $image,
            red: 255,
            green: 255,
            blue: 255,
            alpha: $backdrop->alpha(),
        ));

        GdGuard::call(
            'prepare the image',
            static fn(): bool => (
                imagealphablending($image, enable: false)
                && imagesavealpha($image, enable: true)
                && imagefill($image, x: 0, y: 0, color: $fill)
            ),
        );

        return $image;
    }

    /**
     * @throws GenerationFailedException
     */
    public static function render(GdImage $source, ResizePlan $plan, Backdrop $padding, float $sharpen): GdImage
    {
        $crop = $plan->crop;

        if (null !== $crop) {
            $source = GdGuard::call('crop the image', static fn(): GdImage|false => imagecrop($source, [
                'x'      => $crop->x,
                'y'      => $crop->y,
                'width'  => $crop->width,
                'height' => $crop->height,
            ]));
        }

        $resized = self::blank($plan->resize, Backdrop::Transparent);

        GdGuard::call('resize the image', static fn(): bool => imagecopyresampled(
            $resized,
            $source,
            dst_x: 0,
            dst_y: 0,
            src_x: 0,
            src_y: 0,
            dst_width: $plan->resize->width,
            dst_height: $plan->resize->height,
            src_width: imagesx($source),
            src_height: imagesy($source),
        ));

        if ($sharpen > 0.0) {
            self::sharpen($resized, $sharpen);
        }

        return null === $plan->canvas ? $resized : self::pad($resized, $plan->canvas, $padding);
    }

    /**
     * Centres an image on a larger canvas.
     *
     * @throws GenerationFailedException
     */
    private static function pad(GdImage $image, Dimensions $canvasSize, Backdrop $backdrop): GdImage
    {
        $canvas = self::blank($canvasSize, $backdrop);
        $width  = imagesx($image);
        $height = imagesy($image);

        GdGuard::call('pad the image', static fn(): bool => imagecopy(
            $canvas,
            $image,
            dst_x: intdiv($canvasSize->width - $width, num2: 2),
            dst_y: intdiv($canvasSize->height - $height, num2: 2),
            src_x: 0,
            src_y: 0,
            src_width: $width,
            src_height: $height,
        ));

        return $canvas;
    }

    /**
     * A light sharpening convolution whose strength follows the site's
     * sharpen amount.
     *
     * @throws GenerationFailedException
     */
    private static function sharpen(GdImage $image, float $amount): void
    {
        $edge   = -$amount / 4;
        $matrix = [[0.0, $edge, 0.0], [$edge, 1 + $amount, $edge], [0.0, $edge, 0.0]];

        GdGuard::call('sharpen the image', static fn(): bool => imageconvolution(
            $image,
            $matrix,
            divisor: 1,
            offset: 0,
        ));
    }
}
