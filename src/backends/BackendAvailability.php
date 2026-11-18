<?php

declare(strict_types=1);

namespace parallelogram\images\backends;

use Closure;

/**
 * Decides which backend actually runs, given the PHP extensions installed.
 *
 * @api
 */
final readonly class BackendAvailability
{
    /**
     * @param Closure(string): bool $extensionLoaded Usually `extension_loaded(...)`.
     */
    public function __construct(
        private Closure $extensionLoaded,
    ) {}

    /**
     * The backend that runs for a choice: Auto becomes Imagick or GD, a local
     * backend whose extension is missing becomes null, and remote services
     * are returned as they are.
     */
    public function resolve(BackendKind $kind): ?BackendKind
    {
        if (BackendKind::Auto === $kind) {
            return $this->resolve(BackendKind::Imagick) ?? $this->resolve(BackendKind::Gd);
        }

        if (BackendKind::Imagick === $kind) {
            return ($this->extensionLoaded)('imagick') ? $kind : null;
        }

        if (BackendKind::Gd === $kind) {
            return ($this->extensionLoaded)('gd') ? $kind : null;
        }

        return $kind;
    }
}
