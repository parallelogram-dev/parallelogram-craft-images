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
 * with either PHP's FFI extension (php-vips 2.x) or the `vips` extension
 * (php-vips 1.x) loaded, and the library itself loadable. Both php-vips
 * versions have the same API, so the generator doesn't tell them apart; which
 * extension is used follows from which php-vips the site installed. The
 * final version check is what settles it: php-vips 2.x without FFI, or 1.x
 * without the extension, can't report one.
 *
 * @api
 */
final readonly class VipsProbe
{
    /**
     * @param Closure(): bool $phpVipsInstalled
     * @param Closure(): bool $ffiLoaded
     * @param Closure(): bool $extensionLoaded
     * @param Closure(): string $version Throws when libvips can't be loaded.
     */
    public function __construct(
        private Closure $phpVipsInstalled,
        private Closure $ffiLoaded,
        private Closure $extensionLoaded,
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
            static fn(): bool => extension_loaded('vips'),
            // @mago-expect lint:prefer-first-class-callable
            static fn(): string => Config::version(),
        );
    }

    public function available(): bool
    {
        if (! ($this->phpVipsInstalled)() || ! (($this->ffiLoaded)() || ($this->extensionLoaded)())) {
            return false;
        }

        try {
            return '' !== ($this->version)();
        } catch (Throwable) {
            return false;
        }
    }
}
