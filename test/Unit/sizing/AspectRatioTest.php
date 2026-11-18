<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\InvalidSizeSetException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AspectRatio::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[Group('unit')]
final class AspectRatioTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function invalidRatioProvider(): array
    {
        return [
            'single number' => ['4'],
            'decimal'       => ['1.5:1'],
            'words'         => ['wide'],
            'three parts'   => ['4:3:2'],
        ];
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function nonPositiveProvider(): array
    {
        return [
            'zero width'  => [0, 3],
            'zero height' => [4, 0],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validRatioProvider(): array
    {
        return [
            'colon'          => ['4:3', '4:3'],
            'slash'          => ['16/9', '16:9'],
            'lowercase x'    => ['3x2', '3:2'],
            'multiplication' => ['1×1', '1:1'],
            'spaces'         => [' 21 : 9 ', '21:9'],
        ];
    }

    #[Test]
    public function fitsTheWidestBoxInsideTallerSources(): void
    {
        static::assertSame(800, AspectRatio::fromString('4:3')->widestWithin(1600, height: 600));
    }

    #[Test]
    public function fitsTheWidestBoxInsideWiderSources(): void
    {
        static::assertSame(1000, AspectRatio::fromString('4:3')->widestWithin(1000, height: 2000));
    }

    #[Test]
    public function matchesAHeightToAWidth(): void
    {
        static::assertSame(540, AspectRatio::fromString('16:9')->heightFor(960));
    }

    #[Test]
    public function neverReturnsAZeroHeight(): void
    {
        static::assertSame(1, AspectRatio::fromString('100:1')->heightFor(10));
    }

    #[DataProvider('validRatioProvider')]
    #[Test]
    public function readsCommonRatioSpellings(string $value, string $expected): void
    {
        static::assertSame($expected, AspectRatio::fromString($value)->toString());
    }

    #[Test]
    public function reducesDimensionsToLowestTerms(): void
    {
        static::assertSame('4:3', AspectRatio::of(1600, height: 1200)->toString());
    }

    #[DataProvider('nonPositiveProvider')]
    #[Test]
    public function rejectsSidesBelowOne(int $width, int $height): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('two whole numbers above zero');

        AspectRatio::of($width, $height);
    }

    #[DataProvider('invalidRatioProvider')]
    #[Test]
    public function rejectsTextThatIsNotARatio(string $value): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('is not an aspect ratio');

        AspectRatio::fromString($value);
    }
}
