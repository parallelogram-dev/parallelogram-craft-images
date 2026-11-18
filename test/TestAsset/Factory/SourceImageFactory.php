<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Factory;

use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\SourceImage;

/**
 * Builds valid source images for tests, varying only what a test cares about.
 *
 * @internal
 */
final class SourceImageFactory
{
    public const string UID = '0b1e2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';

    public static function make(
        string $filename = 'harbour-dawn.jpg',
        int $width = 2000,
        int $height = 1500,
        int $modifiedAt = 1_763_000_000,
        ?FocalPoint $focalPoint = null,
    ): SourceImage {
        return new SourceImage(self::UID, $filename, new Dimensions($width, $height), $modifiedAt, $focalPoint);
    }
}
