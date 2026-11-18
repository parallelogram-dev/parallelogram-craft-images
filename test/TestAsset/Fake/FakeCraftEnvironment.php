<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use craft\base\FsInterface;
use craft\elements\Asset;
use Override;
use parallelogram\images\craft\CraftEnvironmentInterface;
use yii\mutex\Mutex;

use function in_array;
use function str_starts_with;
use function substr;

/**
 * A stand-in for a running Craft site. Environment references (`$NAME`)
 * resolve from the given variables, and the asset and filesystem are whatever
 * the test supplies.
 *
 * @internal
 */
final readonly class FakeCraftEnvironment implements CraftEnvironmentInterface
{
    /**
     * @param array<string, string> $env
     * @param list<string> $extensions
     */
    public function __construct(
        private ?FsInterface $filesystem,
        private Mutex $mutex,
        private ?Asset $asset = null,
        private array $env = ['P_IMAGES_SECRET' => 'secret'],
        private array $extensions = ['imagick', 'gd'],
    ) {}

    #[Override]
    public function extensionLoaded(string $extension): bool
    {
        return in_array($extension, $this->extensions, strict: true);
    }

    #[Override]
    public function filesystem(string $handle): ?FsInterface
    {
        return 'variants' === $handle ? $this->filesystem : null;
    }

    #[Override]
    public function findAsset(string $uid): ?Asset
    {
        return $this->asset?->uid === $uid ? $this->asset : null;
    }

    #[Override]
    public function mutex(): Mutex
    {
        return $this->mutex;
    }

    #[Override]
    public function parse(string $value): string
    {
        return str_starts_with($value, '$') ? $this->env[substr($value, offset: 1)] ?? '' : $value;
    }

    #[Override]
    public function siteUrl(): string
    {
        return 'https://www.example.test/';
    }

    #[Override]
    public function volumeOf(string $assetUid): ?string
    {
        return null === $this->findAsset($assetUid) ? null : 'volume-1';
    }
}
