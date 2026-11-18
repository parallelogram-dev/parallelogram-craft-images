<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;

use function is_string;

/**
 * Makes sure the site has a variant secret, writing a random one to `.env`
 * on install if none is set. An existing secret is never replaced, because
 * changing it changes every variant URL.
 *
 * @internal
 */
final readonly class SecretProvisioner
{
    public const string ENV_NAME = 'P_IMAGES_SECRET';

    /**
     * @param Closure(string): mixed $readEnv Reads an environment variable, e.g. `App::env(...)`.
     * @param Closure(string, string): void $writeEnv Writes one to `.env`.
     * @param Closure(): string $generate Makes a new random secret.
     */
    public function __construct(
        private Closure $readEnv,
        private Closure $writeEnv,
        private Closure $generate,
    ) {}

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Writes a new secret if none is set.
     *
     * @return bool Whether a secret was written.
     */
    public function provision(): bool
    {
        if ('' !== self::text(($this->readEnv)(self::ENV_NAME))) {
            return false;
        }

        ($this->writeEnv)(self::ENV_NAME, ($this->generate)());

        return true;
    }
}
