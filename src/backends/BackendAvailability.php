<?php

declare(strict_types=1);

namespace parallelogram\images\backends;

use Closure;

/**
 * Decides which backend actually runs, given the image libraries available.
 *
 * @api
 */
final readonly class BackendAvailability
{
    /**
     * @param Closure(string): bool $hasLibrary Whether `vips`, `imagick` or `gd` can be used.
     */
    public function __construct(
        private Closure $hasLibrary,
    ) {}

    /**
     * The backend that runs for a choice: Auto becomes vips, Imagick or GD (in
     * that order), a local backend whose library is missing becomes null, and
     * remote services are returned as they are.
     */
    public function resolve(BackendKind $kind): ?BackendKind
    {
        if (BackendKind::Auto === $kind) {
            return (
                $this->resolve(BackendKind::Vips)
                    ?? $this->resolve(BackendKind::Imagick)
                    ?? $this->resolve(BackendKind::Gd)
            );
        }

        if (BackendKind::Vips === $kind) {
            return ($this->hasLibrary)('vips') ? $kind : null;
        }

        if (BackendKind::Imagick === $kind) {
            return ($this->hasLibrary)('imagick') ? $kind : null;
        }

        if (BackendKind::Gd === $kind) {
            return ($this->hasLibrary)('gd') ? $kind : null;
        }

        return $kind;
    }
}
