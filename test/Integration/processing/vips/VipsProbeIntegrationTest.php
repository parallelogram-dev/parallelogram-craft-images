<?php

declare(strict_types=1);

namespace parallelogram\images\test\Integration\processing\vips;

use parallelogram\images\processing\vips\VipsProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Checks the real probe on this machine, where php-vips, FFI and libvips are
 * all installed for the vips generator's tests.
 *
 * @internal
 */
#[CoversClass(VipsProbe::class)]
#[Group('integration')]
final class VipsProbeIntegrationTest extends TestCase
{
    #[Test]
    public function findsLibvipsOnThisServer(): void
    {
        static::assertTrue(VipsProbe::forThisServer()->available());
    }
}
