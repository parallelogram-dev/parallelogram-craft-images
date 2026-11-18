<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\processing;

use parallelogram\images\processing\Rect;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Mode;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResizePlan::class)]
#[CoversClass(Rect::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[Group('unit')]
final class ResizePlanTest extends TestCase
{
    /**
     * A 2000×1000 source cropped to a 500×500 box, with the focal point in
     * various places.
     *
     * @return array<string, array{FocalPoint, Rect}>
     */
    public static function focalCropProvider(): array
    {
        return [
            'centre'         => [new FocalPoint(), new Rect(500, 0, 1000, 1000)],
            'left of centre' => [new FocalPoint(0.3, y: 0.5), new Rect(100, 0, 1000, 1000)],
            'far left'       => [new FocalPoint(0.05, y: 0.5), new Rect(0, 0, 1000, 1000)],
            'far right'      => [new FocalPoint(0.95, y: 0.5), new Rect(1000, 0, 1000, 1000)],
        ];
    }

    /**
     * @return array<string, array{Box, ResizePlan}>
     */
    public static function modeProvider(): array
    {
        return [
            'proportional'          => [new Box(500, null, Mode::Crop), new ResizePlan(null, new Dimensions(500, 375))],
            'proportional too wide' => [
                new Box(4000, null, Mode::Fit),
                new ResizePlan(null, new Dimensions(2000, 1500)),
            ],
            'stretch'        => [new Box(500, 500, Mode::Stretch), new ResizePlan(null, new Dimensions(500, 500))],
            'fit'            => [new Box(500, 500, Mode::Fit), new ResizePlan(null, new Dimensions(500, 375))],
            'fit larger box' => [new Box(4000, 4000, Mode::Fit), new ResizePlan(null, new Dimensions(2000, 1500))],
            'letterbox'      => [
                new Box(500, 500, Mode::Letterbox),
                new ResizePlan(null, new Dimensions(500, 375), new Dimensions(500, 500)),
            ],
        ];
    }

    #[DataProvider('focalCropProvider')]
    #[Test]
    public function cropsAroundTheFocalPointWithinTheImage(FocalPoint $focalPoint, Rect $expected): void
    {
        $plan = ResizePlan::for(new Dimensions(2000, 1000), $focalPoint, new Box(500, 500, Mode::Crop));

        static::assertEquals(new ResizePlan($expected, new Dimensions(500, 500)), $plan);
    }

    #[Test]
    public function cropsTallSourcesVertically(): void
    {
        $plan = ResizePlan::for(new Dimensions(1000, 3000), new FocalPoint(0.5, y: 0.2), new Box(400, 300, Mode::Crop));

        static::assertEquals(new ResizePlan(new Rect(0, 225, 1000, 750), new Dimensions(400, 300)), $plan);
    }

    #[DataProvider('modeProvider')]
    #[Test]
    public function plansEachMode(Box $box, ResizePlan $expected): void
    {
        static::assertEquals($expected, ResizePlan::for(new Dimensions(2000, 1500), new FocalPoint(), $box));
    }
}
