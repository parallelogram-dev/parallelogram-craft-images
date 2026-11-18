<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset;

use Closure;

use function array_key_exists;
use function getenv;
use function putenv;

/**
 * Runs code with an environment variable removed everywhere Craft looks for
 * it ($_SERVER, $_ENV and the process environment), then puts it back
 * exactly as it was.
 *
 * Exempt from analysis in mago.toml: it has to touch superglobals.
 *
 * @internal
 */
final class EnvironmentVariables
{
    /**
     * @template T
     *
     * @param Closure(): T $run
     *
     * @return T
     */
    public static function without(string $name, Closure $run): mixed
    {
        $process   = getenv($name);
        $hadServer = array_key_exists($name, $_SERVER);
        $hadEnv    = array_key_exists($name, $_ENV);
        $server    = $_SERVER[$name] ?? null;
        $env       = $_ENV[$name] ?? null;

        putenv($name);
        unset($_SERVER[$name], $_ENV[$name]);

        try {
            return $run();
        } finally {
            putenv(false === $process ? $name : "{$name}={$process}");

            if ($hadServer) {
                $_SERVER[$name] = $server;
            }

            if ($hadEnv) {
                $_ENV[$name] = $env;
            }
        }
    }
}
