<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Override;
use parallelogram\images\delivery\VariantLockInterface;
use yii\mutex\Mutex;

/**
 * Locks variant generation through Craft's mutex, which works across PHP
 * processes and, with a shared mutex driver, across servers.
 *
 * @internal
 */
final readonly class MutexVariantLock implements VariantLockInterface
{
    public function __construct(
        private Mutex $mutex,
        private int $waitSeconds = 10,
    ) {}

    private static function name(string $key): string
    {
        return "p-images:{$key}";
    }

    #[Override]
    public function acquire(string $key): bool
    {
        return $this->mutex->acquire(self::name($key), $this->waitSeconds);
    }

    #[Override]
    public function release(string $key): void
    {
        $this->mutex->release(self::name($key));
    }
}
