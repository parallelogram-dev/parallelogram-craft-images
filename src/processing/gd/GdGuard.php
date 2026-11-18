<?php

declare(strict_types=1);

namespace parallelogram\images\processing\gd;

use Closure;
use parallelogram\images\delivery\GenerationFailedException;

use function restore_error_handler;
use function set_error_handler;

/**
 * Runs one GD call, turning the warning it raises or the `false` it returns
 * on failure into a {@see GenerationFailedException}.
 *
 * @internal
 */
final class GdGuard
{
    /**
     * @template T
     *
     * @param Closure(): (T|false) $operation
     *
     * @return T
     *
     * @throws GenerationFailedException
     */
    public static function call(string $what, Closure $operation): mixed
    {
        $warning = null;

        set_error_handler(
            /**
             * @param int $_level PHP's error level, unused: any warning from a GD call means it failed.
             */
            static function (int $_level, string $message) use (&$warning): bool {
                $warning = $message;

                return true;
            },
        );

        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }

        if (false === $result || null !== $warning) {
            throw new GenerationFailedException("GD couldn't {$what}" . (null === $warning ? '.' : ": {$warning}"));
        }

        return $result;
    }
}
