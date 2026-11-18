<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

/**
 * Where variant files are kept: the chosen Craft filesystem.
 *
 * @api
 */
interface VariantStoreInterface
{
    /**
     * @throws StorageFailedException
     */
    public function exists(string $path): bool;

    /**
     * Whether a file's public URL is on the site's own domain, so the site's
     * web server serves it directly once it exists. When it isn't (a bucket or
     * CDN), requests are redirected there instead.
     */
    public function isOnSiteDomain(): bool;

    /**
     * @throws StorageFailedException
     */
    public function read(string $path): string;

    /**
     * The public URL of a file, or null when the filesystem has none.
     */
    public function urlFor(string $path): ?string;

    /**
     * @throws StorageFailedException
     */
    public function write(string $path, string $contents): void;
}
