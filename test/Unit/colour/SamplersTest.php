<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\colour;

use parallelogram\images\colour\ColourSamplerInterface;
use parallelogram\images\colour\DominantColour;
use parallelogram\images\colour\GdSampler;
use parallelogram\images\colour\ImagickSampler;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\processing\gd\GdGuard;
use parallelogram\images\test\TestAsset\Factory\GdImageFactory;
use parallelogram\images\test\TestAsset\Factory\ImageFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * @internal
 */
#[CoversClass(GdSampler::class)]
#[CoversClass(ImagickSampler::class)]
#[UsesClass(DominantColour::class)]
#[UsesClass(GdGuard::class)]
#[Group('unit')]
final class SamplersTest extends TestCase
{
    /**
     * @return array<string, array{ColourSamplerInterface}>
     */
    public static function samplerProvider(): array
    {
        return ['gd' => [new GdSampler()], 'imagick' => [new ImagickSampler()]];
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('samplerProvider')]
    #[Test]
    public function leavesTransparentPixelsOut(ColourSamplerInterface $sampler): void
    {
        static::assertSame([], $sampler->pixels(ImageFactory::transparentPng(64, height: 64)));
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('samplerProvider')]
    #[Test]
    public function readsPaletteImages(ColourSamplerInterface $sampler): void
    {
        static::assertSame('#008000', DominantColour::of($sampler->pixels(GdImageFactory::paletteGif(40, height: 20))));
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('samplerProvider')]
    #[Test]
    public function refusesBytesThatArentAnImage(ColourSamplerInterface $sampler): void
    {
        $this->expectException(GenerationFailedException::class);

        $sampler->pixels('not an image');
    }

    /**
     * @throws GenerationFailedException
     */
    #[DataProvider('samplerProvider')]
    #[Test]
    public function shrinksTheImageToThirtyTwoPixelsSquare(ColourSamplerInterface $sampler): void
    {
        $pixels = $sampler->pixels(ImageFactory::solid(300, height: 200, colour: 'red', format: 'jpeg'));

        static::assertSame([1024, '#fe0000'], [count($pixels), DominantColour::of($pixels)]);
    }
}
