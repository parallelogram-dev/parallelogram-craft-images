<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\delivery\SourceLookupInterface;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\warming\SweepResult;
use parallelogram\images\warming\VariantCleaner;
use parallelogram\images\warming\VariantWarmer;
use parallelogram\images\warming\WarmResult;

/**
 * Making variants ahead of time, and removing ones nothing can ask for, for
 * Craft assets.
 *
 * Images on volumes that use imgproxy or Cloudflare are skipped: those
 * services make their own variants from URLs, so there's nothing to store.
 *
 * @internal
 */
final readonly class Warming
{
    public function __construct(
        public SizeSetScope $scope,
        private VariantWarmer $warmer,
        private VariantCleaner $cleaner,
        private VariantBackends $backends,
        private SourceLookupInterface $sources,
    ) {}

    /**
     * Removes every variant of a deleted asset.
     *
     * @throws StorageFailedException
     */
    public function forget(string $assetUid): void
    {
        $this->cleaner->removeAll($assetUid);
    }

    /**
     * The files an image should have, that don't exist yet: for one size set
     * when given, otherwise for those assigned to its volume. None for images
     * that aren't resized here.
     *
     * @return list<VariantFile>
     *
     * @throws StorageFailedException
     */
    public function missing(Asset $asset, ?string $sizeSet = null): array
    {
        $source = $this->resizable($asset);

        return null === $source ? [] : $this->warmer->missing($source, $this->sizeSets($asset, $sizeSet));
    }

    /**
     * Deletes every variant of an image and makes the assigned ones again.
     * Null for images that aren't resized here.
     *
     * @throws StorageFailedException
     */
    public function rebuild(Asset $asset): ?WarmResult
    {
        $source = $this->resizable($asset);

        if (null === $source) {
            return null;
        }

        $this->cleaner->removeAll($source->uid);

        return $this->warmer->warm($source, $this->scope->forVolume($asset));
    }

    /**
     * Removes stale variants of every asset, and every variant of assets that
     * are gone.
     *
     * @throws StorageFailedException
     */
    public function sweep(): SweepResult
    {
        return $this->cleaner->sweep($this->sources);
    }

    /**
     * Makes an image's variants in the given size sets. Null for images that
     * aren't resized here.
     *
     * @param list<string> $sizeSets Handles.
     */
    public function warm(Asset $asset, array $sizeSets): ?WarmResult
    {
        $source = $this->resizable($asset);

        return null === $source ? null : $this->warmer->warm($source, $sizeSets);
    }

    /**
     * Makes an image's variants in the given size sets, then, once every file
     * exists, removes any the size sets no longer define, as after the image
     * is replaced. Null for images that aren't resized here.
     *
     * @param list<string> $sizeSets Handles.
     *
     * @throws StorageFailedException
     */
    public function warmAndClean(Asset $asset, array $sizeSets): ?WarmResult
    {
        $source = $this->resizable($asset);

        if (null === $source) {
            return null;
        }

        $result = $this->warmer->warm($source, $sizeSets);

        if (0 === $result->failed) {
            $this->cleaner->removeStale($source);
        }

        return $result;
    }

    /**
     * Makes an image's variants in one size set when given, otherwise in
     * those assigned to its volume. Null for images that aren't resized here.
     */
    public function warmAssigned(Asset $asset, ?string $sizeSet = null): ?WarmResult
    {
        return $this->warm($asset, $this->sizeSets($asset, $sizeSet));
    }

    /**
     * The image's facts, unless it isn't an image to resize here: not
     * resizable at all, or on a volume that uses an image service.
     */
    private function resizable(Asset $asset): ?SourceImage
    {
        try {
            $service = $this->backends->serviceFor(AssetFiles::volumeUid($asset));
        } catch (InvalidVariantException) {
            return null;
        }

        return null === $service ? AssetSource::from($asset) : null;
    }

    /**
     * @return list<string>
     */
    private function sizeSets(Asset $asset, ?string $sizeSet): array
    {
        return null === $sizeSet ? $this->scope->forVolume($asset) : [$sizeSet];
    }
}
