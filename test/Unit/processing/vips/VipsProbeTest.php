<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\processing\vips;

use Jcupitt\Vips\Config;
use parallelogram\images\processing\vips\VipsProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function class_exists;

/**
 * @internal
 */
#[CoversClass(VipsProbe::class)]
#[Group('unit')]
final class VipsProbeTest extends TestCase
{
    /**
     * @return array<string, array{bool, bool, bool, string|null, bool}>
     */
    public static function serverProvider(): array
    {
        return [
            'ffi in place'            => [true, true, false, '8.18.7', true],
            'vips extension in place' => [true, false, true, '8.18.7', true],
            'both in place'           => [true, true, true, '8.18.7', true],
            'php-vips not installed'  => [false, true, true, '8.18.7', false],
            'neither extension'       => [true, false, false, '8.18.7', false],
            'libvips will not load'   => [true, true, false, null, false],
            'no version reported'     => [true, true, false, '', false],
        ];
    }

    /**
     * Craft sites without php-vips installed (it's only suggested) still
     * build this probe on every request; resolving the version check eagerly
     * once crashed them with "Class Jcupitt\Vips\Config not found".
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    #[Test]
    public function canBeBuiltWithoutLoadingPhpVips(): void
    {
        VipsProbe::forThisServer();

        static::assertFalse(class_exists(Config::class, autoload: false));
    }

    #[DataProvider('serverProvider')]
    #[Test]
    public function reportsWhetherLibvipsCanBeUsed(
        bool $installed,
        bool $ffi,
        bool $extension,
        ?string $version,
        bool $expected,
    ): void {
        $probe = new VipsProbe(
            static fn(): bool => $installed,
            static fn(): bool => $ffi,
            static fn(): bool => $extension,
            /**
             * @throws RuntimeException
             */
            static fn(): string => $version ?? throw new RuntimeException('libvips.42.dylib not found'),
        );

        static::assertSame($expected, $probe->available());
    }
}
