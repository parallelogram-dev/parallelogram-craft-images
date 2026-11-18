<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\processing\vips;

use parallelogram\images\processing\vips\VipsProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(VipsProbe::class)]
#[Group('unit')]
final class VipsProbeTest extends TestCase
{
    /**
     * @return array<string, array{bool, bool, string|null, bool}>
     */
    public static function serverProvider(): array
    {
        return [
            'everything in place'    => [true, true, '8.18.7', true],
            'php-vips not installed' => [false, true, '8.18.7', false],
            'ffi not loaded'         => [true, false, '8.18.7', false],
            'libvips will not load'  => [true, true, null, false],
            'no version reported'    => [true, true, '', false],
        ];
    }

    #[DataProvider('serverProvider')]
    #[Test]
    public function reportsWhetherLibvipsCanBeUsed(bool $installed, bool $ffi, ?string $version, bool $expected): void
    {
        $probe = new VipsProbe(
            static fn(): bool => $installed,
            static fn(): bool => $ffi,
            /**
             * @throws RuntimeException
             */
            static fn(): string => $version ?? throw new RuntimeException('libvips.42.dylib not found'),
        );

        static::assertSame($expected, $probe->available());
    }
}
