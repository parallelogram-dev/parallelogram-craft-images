<?php

declare(strict_types=1);

namespace parallelogram\images\warming;

use parallelogram\images\delivery\SourceLookupInterface;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantFoldersInterface;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;

use function in_array;
use function preg_match;

/**
 * Removes variants nothing can ask for any more: files left behind when an
 * image is replaced or a size set changes, and every file of a deleted image.
 *
 * Nothing is ever removed while no size sets are defined, so a settings
 * mistake can't wipe every variant.
 *
 * @api
 */
final readonly class VariantCleaner
{
    private const string UID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function __construct(
        private VariantPath $path,
        private VariantPlanner $planner,
        private SizeSetCatalogue $catalogue,
        private VariantFoldersInterface $folders,
    ) {}

    /**
     * Removes every variant of an asset, such as a deleted one.
     *
     * @throws StorageFailedException
     */
    public function removeAll(string $assetUid): void
    {
        $this->folders->deleteFolder($this->path->folderFor($assetUid));
    }

    /**
     * Removes the folders of an image's variants that no size set defines for
     * it as it is now, returning how many.
     *
     * @throws StorageFailedException
     */
    public function removeStale(SourceImage $source): int
    {
        if ([] === $this->catalogue->all()) {
            return 0;
        }

        $wanted = [];

        foreach ($this->planner->filesForAll($source, $this->catalogue) as $file) {
            $wanted[] = $file->hash;
        }

        $folder  = $this->path->folderFor($source->uid);
        $removed = 0;

        foreach ($this->folders->folders($folder) as $hash) {
            if (in_array($hash, $wanted, strict: true)) {
                continue;
            }

            $this->folders->deleteFolder("{$folder}/{$hash}");
            ++$removed;
        }

        return $removed;
    }

    /**
     * Goes through every asset's folder: those of assets that no longer
     * exist as resizable images are removed, and the rest are cleared of
     * stale variants. Folders not named like an asset UID are left alone.
     *
     * @throws StorageFailedException
     */
    public function sweep(SourceLookupInterface $sources): SweepResult
    {
        $result = new SweepResult();

        if ([] === $this->catalogue->all()) {
            return $result;
        }

        foreach ($this->folders->folders($this->path->prefix) as $name) {
            if (1 !== preg_match(self::UID_PATTERN, $name)) {
                continue;
            }

            $result = $this->sweepFolder($sources, $name, $result);
        }

        return $result;
    }

    /**
     * @throws StorageFailedException
     */
    private function sweepFolder(SourceLookupInterface $sources, string $uid, SweepResult $soFar): SweepResult
    {
        $source = $sources->find($uid);

        if (null === $source) {
            $this->removeAll($uid);

            return new SweepResult($soFar->assetFolders + 1, $soFar->staleFolders, $soFar->checkedAssets);
        }

        return new SweepResult(
            $soFar->assetFolders,
            $soFar->staleFolders + $this->removeStale($source),
            $soFar->checkedAssets + 1,
        );
    }
}
