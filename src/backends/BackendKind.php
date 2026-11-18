<?php

declare(strict_types=1);

namespace parallelogram\images\backends;

/**
 * Which backend makes a volume's variants.
 *
 * @api
 */
enum BackendKind: string
{
    /** libvips when it's usable, otherwise ImageMagick, otherwise GD. */
    case Auto = 'auto';

    /** Cloudflare Images, linked to directly. */
    case Cloudflare = 'cloudflare';

    /** PHP's GD extension. */
    case Gd = 'gd';

    /** ImageMagick through the Imagick extension. */
    case Imagick = 'imagick';

    /** A self-hosted imgproxy server, linked to directly. */
    case Imgproxy = 'imgproxy';

    /** libvips through php-vips and PHP's FFI extension. */
    case Vips = 'vips';

    /**
     * Whether a separate service makes the images, so pages link to it and
     * nothing is stored or warmed locally.
     */
    public function isRemote(): bool
    {
        return self::Cloudflare === $this || self::Imgproxy === $this;
    }
}
