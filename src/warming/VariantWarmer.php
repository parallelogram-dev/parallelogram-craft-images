<?php

declare(strict_types=1);

namespace parallelogram\images\warming;

use parallelogram\images\delivery\MakeResult;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantMaker;
use parallelogram\images\delivery\VariantStoreInterface;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\variants\VariantPlanner;

use function array_push;
use function in_array;

/**
 * Makes the variants of an image ahead of time, so the first visitor doesn't
 * wait for them, and reports which are missing.
 *
 * @api
 */
final readonly class VariantWarmer
{
    public function __construct(
        private VariantPlanner $planner,
        private SizeSetCatalogue $catalogue,
        private VariantMaker $maker,
        private VariantStoreInterface $store,
    ) {}

    /**
     * The files of the given size sets that don't exist yet. Size sets that
     * no longer exist are ignored.
     *
     * @param list<string> $sizeSets Handles.
     *
     * @return list<VariantFile>
     *
     * @throws StorageFailedException
     */
    public function missing(SourceImage $source, array $sizeSets): array
    {
        $missing = [];

        foreach ($this->files($source, $sizeSets) as $file) {
            if ($this->store->exists($file->path)) {
                continue;
            }

            $missing[] = $file;
        }

        return $missing;
    }

    /**
     * Makes every missing file of the given size sets. A file that can't be
     * stored is counted as failed and the rest are still tried.
     *
     * @param list<string> $sizeSets Handles.
     */
    public function warm(SourceImage $source, array $sizeSets): WarmResult
    {
        $result = new WarmResult();

        foreach ($this->files($source, $sizeSets) as $file) {
            $result = $result->with($this->ensure($source, $file));
        }

        return $result;
    }

    private function ensure(SourceImage $source, VariantFile $file): MakeResult
    {
        try {
            return $this->maker->ensure($source, $file);
        } catch (StorageFailedException) {
            return MakeResult::Failed;
        }
    }

    /**
     * @param list<string> $sizeSets
     *
     * @return list<VariantFile>
     */
    private function files(SourceImage $source, array $sizeSets): array
    {
        $files = [];

        foreach ($this->catalogue->all() as $sizeSet) {
            if (! in_array($sizeSet->handle, $sizeSets, strict: true)) {
                continue;
            }

            array_push($files, ...$this->planner->filesFor($source, $sizeSet));
        }

        return $files;
    }
}
