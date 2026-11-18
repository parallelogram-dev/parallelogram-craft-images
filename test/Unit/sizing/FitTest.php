<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Mode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Fit::class)]
#[CoversClass(Box::class)]
#[UsesClass(AspectRatio::class)]
#[Group('unit')]
final class FitTest extends TestCase
{
    /**
     * @return array<string, array{Fit, int}>
     */
    public static function widestProvider(): array
    {
        $ratio = new AspectRatio(1, 1);

        return [
            'no ratio'        => [new Fit(), 2000],
            'cropped ratio'   => [new Fit(Mode::Crop, $ratio), 500],
            'fitted ratio'    => [new Fit(Mode::Fit, $ratio), 2000],
            'letterbox ratio' => [new Fit(Mode::Letterbox, $ratio), 2000],
        ];
    }

    #[Test]
    public function givesARatioBoxItsHeight(): void
    {
        $fit = new Fit(Mode::Crop, new AspectRatio(4, 3));

        static::assertEquals(new Box(800, 600, Mode::Crop), $fit->boxFor(800));
    }

    #[Test]
    public function leavesTheHeightOpenWithoutARatio(): void
    {
        static::assertEquals(new Box(800, null, Mode::Fit), (new Fit(Mode::Fit))->boxFor(800));
    }

    #[DataProvider('widestProvider')]
    #[Test]
    public function limitsWidthByHeightOnlyForCroppedRatios(Fit $fit, int $expected): void
    {
        static::assertSame($expected, $fit->widestFor(2000, sourceHeight: 500));
    }
}
