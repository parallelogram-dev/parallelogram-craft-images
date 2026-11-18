<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

/**
 * How a source image is fitted to a variant's box.
 *
 * @api
 */
enum Mode: string
{
    /** Fill the box, cropping around the focal point. */
    case Crop = 'crop';

    /** Fit inside the box, keeping the whole image. */
    case Fit = 'fit';

    /** Fit inside the box and pad the rest. */
    case Letterbox = 'letterbox';

    /** Fill the box, distorting the image. */
    case Stretch = 'stretch';
}
