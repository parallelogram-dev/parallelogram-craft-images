<?php

declare(strict_types=1);

namespace parallelogram\images\colour;

use Imagick;
use ImagickException;
use Override;
use parallelogram\images\delivery\GenerationFailedException;

use function ord;
use function str_split;
use function substr;

/**
 * Samples pixels with ImageMagick.
 *
 * @api
 */
final class ImagickSampler implements ColourSamplerInterface
{
    #[Override]
    public function pixels(string $bytes): array
    {
        try {
            $image = new Imagick();
            $image->readImageBlob($bytes);
            $image->setIteratorIndex(0);
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            $image->thumbnailImage(self::SIZE, self::SIZE, bestfit: false);
            $image->setImageDepth(8);
            $image->setImageFormat('RGBA');
            $raw = $image->getImageBlob();
        } catch (ImagickException $exception) {
            throw new GenerationFailedException(
                "ImageMagick couldn't sample the image: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $pixels = [];

        foreach (str_split($raw, length: 4) as $pixel) {
            if (ord(substr($pixel, offset: 3, length: 1)) < 128) {
                continue;
            }

            $pixels[] = [
                ord($pixel),
                ord(substr($pixel, offset: 1, length: 1)),
                ord(substr($pixel, offset: 2, length: 1)),
            ];
        }

        return $pixels;
    }
}
