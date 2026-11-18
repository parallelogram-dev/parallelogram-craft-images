<?php

declare(strict_types=1);

namespace parallelogram\images\processing\gd;

use GdImage;
use parallelogram\images\delivery\GenerationFailedException;

use function base64_encode;
use function exif_read_data;
use function imageflip;
use function imagerotate;
use function is_int;
use function str_starts_with;

use const IMG_FLIP_HORIZONTAL;
use const IMG_FLIP_VERTICAL;

/**
 * Applies a JPEG's EXIF orientation, which GD ignores. Each orientation is a
 * counter-clockwise rotation followed by an optional flip, matching what
 * ImageMagick's auto-orient produces.
 *
 * @internal
 */
final class GdOrientation
{
    /**
     * @var array<int, array{int, int|null}> EXIF orientation => [degrees counter-clockwise, flip mode]
     */
    private const array STEPS = [
        2 => [0, IMG_FLIP_HORIZONTAL],
        3 => [180, null],
        4 => [0, IMG_FLIP_VERTICAL],
        5 => [270, IMG_FLIP_HORIZONTAL],
        6 => [270, null],
        7 => [90, IMG_FLIP_HORIZONTAL],
        8 => [90, null],
    ];

    /**
     * @throws GenerationFailedException
     */
    public static function apply(GdImage $image, int $orientation): GdImage
    {
        [$degrees, $flip] = self::STEPS[$orientation] ?? [0, null];

        if (0 !== $degrees) {
            $image = GdGuard::call('rotate the image', static fn(): GdImage|false => imagerotate(
                $image,
                $degrees,
                background_color: 0,
            ));
        }

        if (null !== $flip) {
            GdGuard::call('flip the image', static fn(): bool => imageflip($image, $flip));
        }

        return $image;
    }

    /**
     * The EXIF orientation of a JPEG, or 1 (upright) when it has none.
     */
    public static function read(string $bytes): int
    {
        if (! str_starts_with($bytes, "\xFF\xD8")) {
            return 1;
        }

        try {
            $exif = GdGuard::call(
                'read EXIF data',
                /**
                 * @return array<array-key, mixed>|false
                 */
                static fn(): array|false => exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes)),
            );
        } catch (GenerationFailedException) {
            return 1;
        }

        return is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
    }
}
