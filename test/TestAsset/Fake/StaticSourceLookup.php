<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Override;
use parallelogram\images\delivery\SourceLookupInterface;
use parallelogram\images\variants\SourceImage;

/**
 * Finds only the source images it was given.
 *
 * @internal
 */
final readonly class StaticSourceLookup implements SourceLookupInterface
{
    /**
     * @param list<SourceImage> $sources
     */
    public function __construct(
        private array $sources,
    ) {}

    #[Override]
    public function find(string $assetUid): ?SourceImage
    {
        foreach ($this->sources as $source) {
            if ($source->uid === $assetUid) {
                return $source;
            }
        }

        return null;
    }
}
