<?php

declare(strict_types=1);

namespace parallelogram\images\backends;

/**
 * The site's backend, and the volumes that use a different one.
 *
 * @api
 */
final readonly class BackendChoice
{
    /**
     * @param array<string, BackendKind> $volumes Keyed by volume UID.
     */
    public function __construct(
        public BackendKind $default = BackendKind::Auto,
        public array $volumes = [],
    ) {}

    /**
     * The backend for a volume, or the site's when it has none of its own.
     */
    public function forVolume(?string $volumeUid): BackendKind
    {
        return null === $volumeUid ? $this->default : $this->volumes[$volumeUid] ?? $this->default;
    }

    /**
     * Whether the site or any volume uses a backend.
     */
    public function uses(BackendKind $kind): bool
    {
        if ($kind === $this->default) {
            return true;
        }

        foreach ($this->volumes as $volumeKind) {
            if ($volumeKind === $kind) {
                return true;
            }
        }

        return false;
    }
}
