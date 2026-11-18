<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\processing\gd;

use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\processing\gd\GdGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function trigger_error;

use const E_USER_WARNING;

/**
 * @internal
 */
#[CoversClass(GdGuard::class)]
#[UsesClass(GenerationFailedException::class)]
#[Group('unit')]
final class GdGuardTest extends TestCase
{
    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function failsOnASilentFalse(): void
    {
        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('GD couldn\'t resize the image.');

        GdGuard::call('resize the image', static fn(): false => false);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function failsOnAWarningEvenWhenAValueIsReturned(): void
    {
        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('GD couldn\'t read the original: corrupt data');

        GdGuard::call('read the original', static function (): bool {
            trigger_error('corrupt data', E_USER_WARNING);

            return true;
        });
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function returnsTheResultOfASuccessfulCall(): void
    {
        static::assertSame(42, GdGuard::call('count', static fn(): int => 42));
    }
}
