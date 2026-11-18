<?php

declare(strict_types=1);

namespace parallelogram\images\colour;

use function round;
use function sprintf;

/**
 * Picks an image's most common colour from a small sample of its pixels.
 *
 * Similar colours are grouped by keeping the top four bits of each channel
 * (4,096 groups), the largest group wins (the first to reach that size on a
 * tie), and the answer is the average of that group's pixels, so it's a
 * colour the image really has rather than a muddy mix of everything.
 *
 * @api
 */
final class DominantColour
{
    /**
     * @param list<array{int, int, int}> $pixels Opaque pixels as red, green and blue from 0 to 255.
     *
     * @return string|null `#rrggbb`, or null when there are no pixels.
     */
    public static function of(array $pixels): ?string
    {
        $groups = [];

        foreach ($pixels as [$red, $green, $blue]) {
            $key   = (($red >> 4) << 8) | (($green >> 4) << 4) | ($blue >> 4);
            $group = $groups[$key] ?? [0, 0, 0, 0];

            $groups[$key] = [$group[0] + 1, $group[1] + $red, $group[2] + $green, $group[3] + $blue];
        }

        $largest = null;

        foreach ($groups as $group) {
            if (null !== $largest && $group[0] <= $largest[0]) {
                continue;
            }

            $largest = $group;
        }

        if (null === $largest) {
            return null;
        }

        return sprintf(
            '#%02x%02x%02x',
            (int) round($largest[1] / $largest[0]),
            (int) round($largest[2] / $largest[0]),
            (int) round($largest[3] / $largest[0]),
        );
    }
}
