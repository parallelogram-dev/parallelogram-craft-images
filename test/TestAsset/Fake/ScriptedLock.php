<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Closure;
use Override;
use parallelogram\images\delivery\VariantLockInterface;

/**
 * A lock that is free or held by someone else, as the test decides. An
 * optional callback runs on acquire, to simulate another request finishing
 * its work while this one waited.
 *
 * @internal
 */
final class ScriptedLock implements VariantLockInterface
{
    /**
     * @var list<string>
     */
    public array $released = [];

    /**
     * @param (Closure(): void)|null $onAcquire
     */
    public function __construct(
        private readonly bool $free = true,
        private readonly ?Closure $onAcquire = null,
    ) {}

    #[Override]
    public function acquire(string $key): bool
    {
        if (null !== $this->onAcquire) {
            ($this->onAcquire)();
        }

        return $this->free;
    }

    #[Override]
    public function release(string $key): void
    {
        $this->released[] = $key;
    }
}
