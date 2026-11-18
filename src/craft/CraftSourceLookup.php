<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;
use craft\elements\Asset;
use Override;
use parallelogram\images\delivery\SourceLookupInterface;
use parallelogram\images\variants\SourceImage;

/**
 * Finds a source image by asset UID through Craft.
 *
 * @internal
 */
final readonly class CraftSourceLookup implements SourceLookupInterface
{
    /**
     * @param Closure(string): ?Asset $findAsset Looks an asset up by UID, e.g. with an asset query.
     */
    public function __construct(
        private Closure $findAsset,
    ) {}

    #[Override]
    public function find(string $assetUid): ?SourceImage
    {
        $asset = ($this->findAsset)($assetUid);

        return null === $asset ? null : AssetSource::from($asset);
    }
}
