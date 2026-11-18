<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\colour;

use parallelogram\images\colour\HexColour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HexColour::class)]
#[Group('unit')]
final class HexColourTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function notAColourProvider(): array
    {
        return [
            'null'         => [null],
            'no hash'      => ['7a8b6c'],
            'short form'   => ['#abc'],
            'not hex'      => ['#7a8b6g'],
            'not a string' => [0x7A_8B_6C],
        ];
    }

    #[Test]
    public function keepsSixDigitHexInLowerCase(): void
    {
        static::assertSame('#7a8b6c', HexColour::normalise('#7A8B6C'));
    }

    #[DataProvider('notAColourProvider')]
    #[Test]
    public function refusesAnythingElse(mixed $value): void
    {
        static::assertNull(HexColour::normalise($value));
    }
}
