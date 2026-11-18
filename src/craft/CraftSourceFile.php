<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;
use craft\elements\Asset;
use Override;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\SourceFileInterface;
use parallelogram\images\variants\SourceImage;
use Throwable;

/**
 * Reads an original image through its Craft asset, from whichever filesystem
 * its volume uses.
 *
 * @internal
 */
final readonly class CraftSourceFile implements SourceFileInterface
{
    /**
     * @param Closure(string): ?Asset $findAsset Looks an asset up by UID.
     */
    public function __construct(
        private Closure $findAsset,
    ) {}

    /**
     * Any failure reading the file, whatever Craft or the filesystem throws,
     * becomes a generation failure.
     */
    #[Override]
    public function contents(SourceImage $source): string
    {
        $asset = ($this->findAsset)($source->uid);

        if (null === $asset) {
            throw new GenerationFailedException("Asset {$source->uid} no longer exists.");
        }

        try {
            return $asset->getContents();
        } catch (Throwable $exception) {
            throw new GenerationFailedException(
                "Couldn't read the original file for asset {$source->uid}.",
                previous: $exception,
            );
        }
    }
}
