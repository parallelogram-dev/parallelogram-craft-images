<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\variants;

use parallelogram\images\variants\Processing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Processing::class)]
#[Group('unit')]
final class ProcessingTest extends TestCase
{
    /**
     * @return array<string, array{int, int}>
     */
    public static function effortProvider(): array
    {
        return [
            'slowest and smallest' => [9, 0],
            'the default'          => [3, 6],
            'quickest and largest' => [0, 9],
        ];
    }

    #[DataProvider('effortProvider')]
    #[Test]
    public function convertsEffortToTheEncodersSpeed(int $effort, int $speed): void
    {
        static::assertSame($speed, (new Processing(avifEffort: $effort))->avifSpeed());
    }

    #[Test]
    public function describesOnlyWhatChangesPixels(): void
    {
        static::assertSame(
            (new Processing(avifEffort: 0))->toString(),
            (new Processing(avifEffort: 9))->toString(),
        );
    }
}
