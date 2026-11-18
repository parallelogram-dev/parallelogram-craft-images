<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

/**
 * Stops two requests generating the same file at once.
 *
 * @api
 */
interface VariantLockInterface
{
    /**
     * Waits briefly for the lock. False when another request still holds it.
     */
    public function acquire(string $key): bool;

    public function release(string $key): void;
}
