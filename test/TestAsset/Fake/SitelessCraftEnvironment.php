<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use craft\base\FsInterface;
use craft\elements\Asset;
use Override;
use parallelogram\images\craft\CraftEnvironmentInterface;
use yii\base\Exception;
use yii\mutex\Mutex;

/**
 * Wraps another environment but has no site, as a Craft install whose sites
 * are missing would.
 *
 * @internal
 */
final readonly class SitelessCraftEnvironment implements CraftEnvironmentInterface
{
    public function __construct(
        private CraftEnvironmentInterface $inner,
    ) {}

    #[Override]
    public function extensionLoaded(string $extension): bool
    {
        return $this->inner->extensionLoaded($extension);
    }

    #[Override]
    public function filesystem(string $handle): ?FsInterface
    {
        return $this->inner->filesystem($handle);
    }

    #[Override]
    public function findAsset(string $uid): ?Asset
    {
        return $this->inner->findAsset($uid);
    }

    #[Override]
    public function mutex(): Mutex
    {
        return $this->inner->mutex();
    }

    #[Override]
    public function parse(string $value): string
    {
        return $this->inner->parse($value);
    }

    #[Override]
    public function siteUrl(): string
    {
        throw new Exception('No site.');
    }

    #[Override]
    public function volumeOf(string $assetUid): ?string
    {
        return $this->inner->volumeOf($assetUid);
    }
}
