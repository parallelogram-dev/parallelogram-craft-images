<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Override;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantFoldersInterface;
use parallelogram\images\delivery\VariantStoreInterface;

use function array_key_exists;
use function array_keys;
use function explode;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A variant store held in memory. A store with a base URL that isn't on the
 * site's domain behaves like a bucket or CDN, and a broken one fails every
 * existence check.
 *
 * @internal
 */
final class InMemoryVariantStore implements VariantStoreInterface, VariantFoldersInterface
{
    /**
     * @var array<string, string>
     */
    public array $files = [];

    public function __construct(
        private readonly ?string $baseUrl = '/files',
        private readonly bool $onSiteDomain = true,
        private readonly bool $broken = false,
    ) {}

    #[Override]
    public function deleteFolder(string $path): void
    {
        if ($this->broken) {
            throw new StorageFailedException("Couldn't delete {$path}.");
        }

        foreach (array_keys($this->files) as $file) {
            if (! str_starts_with($file, "{$path}/")) {
                continue;
            }

            unset($this->files[$file]);
        }
    }

    #[Override]
    public function exists(string $path): bool
    {
        if ($this->broken) {
            throw new StorageFailedException("Couldn't check for {$path}.");
        }

        return array_key_exists($path, $this->files);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function folders(string $path): array
    {
        if ($this->broken) {
            throw new StorageFailedException("Couldn't list {$path}.");
        }

        $names = [];

        foreach (array_keys($this->files) as $file) {
            $relative = str_starts_with($file, "{$path}/") ? substr($file, strlen($path) + 1) : '';

            if (str_contains($relative, '/')) {
                $names[explode('/', $relative)[0]] = true;
            }
        }

        return array_keys($names);
    }

    #[Override]
    public function isOnSiteDomain(): bool
    {
        return $this->onSiteDomain;
    }

    #[Override]
    public function read(string $path): string
    {
        return $this->files[$path] ?? '';
    }

    #[Override]
    public function urlFor(string $path): ?string
    {
        return null === $this->baseUrl ? null : "{$this->baseUrl}/{$path}";
    }

    #[Override]
    public function write(string $path, string $contents): void
    {
        $this->files[$path] = $contents;
    }
}
