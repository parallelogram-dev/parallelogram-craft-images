<?php

declare(strict_types=1);

namespace parallelogram\images\colour;

use GdImage;
use Override;
use parallelogram\images\processing\gd\GdGuard;

use function imagecolorat;
use function imagecreatefromstring;
use function imageistruecolor;
use function imagepalettetotruecolor;
use function imagescale;
use function imagesx;
use function imagesy;

/**
 * Samples pixels with PHP's GD extension.
 *
 * @api
 */
final class GdSampler implements ColourSamplerInterface
{
    #[Override]
    public function pixels(string $bytes): array
    {
        $image = GdGuard::call('read the original', static fn(): GdImage|false => imagecreatefromstring($bytes));

        if (! imageistruecolor($image)) {
            GdGuard::call('convert the palette', static fn(): bool => imagepalettetotruecolor($image));
        }

        $small = GdGuard::call('shrink the image', static fn(): GdImage|false => imagescale(
            $image,
            self::SIZE,
            self::SIZE,
        ));
        $pixels = [];

        for ($y = 0, $height = imagesy($small); $y < $height; ++$y) {
            for ($x = 0, $width = imagesx($small); $x < $width; ++$x) {
                $colour = (int) imagecolorat($small, $x, $y);

                if ((($colour >> 24) & 0x7F) >= 64) {
                    continue;
                }

                $pixels[] = [($colour >> 16) & 0xFF, ($colour >> 8) & 0xFF, $colour & 0xFF];
            }
        }

        return $pixels;
    }
}
