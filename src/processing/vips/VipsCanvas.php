<?php

declare(strict_types=1);

namespace parallelogram\images\processing\vips;

use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image;
use parallelogram\images\processing\Backdrop;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\variants\Dimensions;

use function intdiv;

/**
 * Carries out a {@see ResizePlan} with libvips: crop, Lanczos resize,
 * sharpen, then pad.
 *
 * @internal
 */
final class VipsCanvas
{
    private const array WHITE = [255, 255, 255];

    /**
     * @throws Exception
     */
    public static function render(Image $image, ResizePlan $plan, Backdrop $padding, float $sharpen): Image
    {
        if (null !== $plan->crop) {
            $image = $image->crop($plan->crop->x, $plan->crop->y, $plan->crop->width, $plan->crop->height);
        }

        $image = $image->resize($plan->resize->width / $image->width, [
            'vscale' => $plan->resize->height / $image->height,
            'kernel' => 'lanczos3',
        ]);

        if ($sharpen > 0.0) {
            $image = $image->sharpen(['sigma' => 0.7, 'm2' => $sharpen * 2]);
        }

        return null === $plan->canvas ? $image : self::pad($image, $plan->canvas, $padding);
    }

    /**
     * Centres an image on a canvas of the given size, padded with the
     * backdrop.
     *
     * @throws Exception
     */
    private static function pad(Image $image, Dimensions $canvas, Backdrop $backdrop): Image
    {
        if (Backdrop::Transparent === $backdrop && ! $image->hasAlpha()) {
            $image = $image->bandjoin_const([255]);
        }

        return $image->embed(
            intdiv($canvas->width - $image->width, num2: 2),
            intdiv($canvas->height - $image->height, num2: 2),
            $canvas->width,
            $canvas->height,
            ['extend' => 'background', 'background' => Backdrop::White === $backdrop ? self::WHITE : [0, 0, 0, 0]],
        );
    }
}
