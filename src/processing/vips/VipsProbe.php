<?php

declare(strict_types=1);

namespace parallelogram\images\processing\vips;

use Closure;
use Jcupitt\Vips\Config;
use Throwable;

use function class_exists;
use function extension_loaded;

/**
 * Whether libvips can be used in this request: php-vips must be installed,
 * PHP's FFI extension loaded and enabled for runtime use (not only
 * preloading), and the library itself loadable.
 *
 * @api
 */
final readonly class VipsProbe
{
    /**
     * @param Closure(): bool $phpVipsInstalled
     * @param Closure(): bool $ffiLoaded
     * @param Closure(): string $version Throws when libvips can't be loaded.
     */
    public function __construct(
        private Closure $phpVipsInstalled,
        private Closure $ffiLoaded,
        private Closure $version,
    ) {}

    /**
     * Every check is a closure that only touches php-vips when it runs, so
     * building this on a server without php-vips installed is safe.
     */
    public static function forThisServer(): self
    {
        return new self(
            static fn(): bool => class_exists(Config::class),
            static fn(): bool => extension_loaded('ffi'),
            // @mago-expect lint:prefer-first-class-callable
            static fn(): string => Config::version(),
        );
    }

    public function available(): bool
    {
        if (! ($this->phpVipsInstalled)() || ! ($this->ffiLoaded)()) {
            return false;
        }

        try {
            return '' !== ($this->version)();
        } catch (Throwable) {
            return false;
        }
    }
}
