<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\base\FsInterface;
use craft\errors\FsException;
use Override;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantStoreInterface;

use function array_map;
use function explode;
use function implode;
use function is_string;
use function parse_url;
use function rawurlencode;
use function rtrim;
use function str_starts_with;

use const PHP_URL_HOST;

/**
 * Keeps variants on a Craft filesystem.
 *
 * @internal
 */
final readonly class FsVariantStore implements VariantStoreInterface
{
    public function __construct(
        private FsInterface $fs,
        private bool $onSiteDomain,
    ) {}

    /**
     * A store for a filesystem, working out whether its URLs are on the site's
     * own domain: a root-relative URL, or an absolute one on the site's host.
     */
    public static function forFilesystem(FsInterface $fs, string $siteUrl): self
    {
        $rootUrl = $fs->getRootUrl() ?? '';

        if ('' === $rootUrl) {
            return new self($fs, false);
        }

        if (str_starts_with($rootUrl, '/') && ! str_starts_with($rootUrl, '//')) {
            return new self($fs, true);
        }

        $rootHost = parse_url($rootUrl, PHP_URL_HOST);
        $siteHost = parse_url($siteUrl, PHP_URL_HOST);

        return new self($fs, is_string($rootHost) && $rootHost === $siteHost);
    }

    #[Override]
    public function exists(string $path): bool
    {
        try {
            return $this->fs->fileExists($path);
        } catch (FsException $exception) {
            throw new StorageFailedException("Couldn't check for {$path}.", previous: $exception);
        }
    }

    #[Override]
    public function isOnSiteDomain(): bool
    {
        return $this->onSiteDomain;
    }

    #[Override]
    public function read(string $path): string
    {
        try {
            return $this->fs->read($path);
        } catch (FsException $exception) {
            throw new StorageFailedException("Couldn't read {$path}.", previous: $exception);
        }
    }

    #[Override]
    public function urlFor(string $path): ?string
    {
        $rootUrl = $this->fs->getRootUrl();

        if (null === $rootUrl) {
            return null;
        }

        return rtrim($rootUrl, characters: '/') . '/' . implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    #[Override]
    public function write(string $path, string $contents): void
    {
        try {
            $this->fs->write($path, $contents);
        } catch (FsException $exception) {
            throw new StorageFailedException("Couldn't write {$path}.", previous: $exception);
        }
    }
}
