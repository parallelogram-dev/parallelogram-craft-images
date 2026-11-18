<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use parallelogram\images\craft\MutexVariantLock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use yii\mutex\Mutex;

/**
 * @internal
 */
#[CoversClass(MutexVariantLock::class)]
#[Group('unit')]
final class MutexVariantLockTest extends TestCase
{
    #[Test]
    public function acquiresANamespacedLockWithAWait(): void
    {
        $wait  = 3;
        $mutex = $this->createMock(Mutex::class);
        $mutex->expects(static::once())
            ->method('acquire')
            ->with('p-images:asset/variant/a.avif', $wait)
            ->willReturn(true);

        static::assertTrue((new MutexVariantLock($mutex, $wait))->acquire('asset/variant/a.avif'));
    }

    #[Test]
    public function releasesTheSameLock(): void
    {
        $mutex = $this->createMock(Mutex::class);
        $mutex->expects(static::once())->method('release')->with('p-images:asset/variant/a.avif');

        (new MutexVariantLock($mutex))->release('asset/variant/a.avif');
    }
}
