<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\colour;

use parallelogram\images\colour\DominantColour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DominantColour::class)]
#[Group('unit')]
final class DominantColourTest extends TestCase
{
    #[Test]
    public function averagesTheLargestGroupOfSimilarColours(): void
    {
        static::assertSame('#fc0a0a', DominantColour::of([
            [0, 0, 255],
            [250, 10, 10],
            [252, 12, 8],
            [0, 0, 250],
            [254, 8, 12],
        ]));
    }

    #[Test]
    public function groupsByTheTopFourBitsOfEachChannel(): void
    {
        static::assertSame('#180000', DominantColour::of([[15, 0, 0], [16, 0, 0], [31, 0, 0]]));
    }

    #[Test]
    public function hasNoColourWithoutPixels(): void
    {
        static::assertNull(DominantColour::of([]));
    }

    #[Test]
    public function keepsTheFirstGroupOnATie(): void
    {
        static::assertSame('#00ff00', DominantColour::of([[0, 255, 0], [255, 0, 255]]));
    }
}
