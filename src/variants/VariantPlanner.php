<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;

use function array_values;

/**
 * Works out which files an image should have, and which variant a requested
 * hash refers to.
 *
 * @api
 */
final readonly class VariantPlanner
{
    public function __construct(
        private VariantHasher $hasher,
        private VariantPath $path,
        private QualityTable $siteQuality,
    ) {}

    /**
     * Every file a size set needs for an image.
     *
     * @return list<VariantFile>
     */
    public function filesFor(SourceImage $source, SizeSet $sizeSet): array
    {
        $files = [];

        foreach ($sizeSet->variantsFor(
            $source->dimensions->width,
            $source->dimensions->height,
            $this->siteQuality,
        ) as $variant) {
            $hash    = $this->hasher->hash($source, $variant);
            $files[] = new VariantFile($variant, $hash, $this->path->pathFor($source, $variant->format, $hash));
        }

        return $files;
    }

    /**
     * Every file any size set needs for an image, each listed once even when
     * several size sets share it.
     *
     * @return list<VariantFile>
     */
    public function filesForAll(SourceImage $source, SizeSetCatalogue $catalogue): array
    {
        $byPath = [];

        foreach ($catalogue->all() as $sizeSet) {
            foreach ($this->filesFor($source, $sizeSet) as $file) {
                $byPath[$file->path] ??= $file;
            }
        }

        return array_values($byPath);
    }

    /**
     * The file a requested path refers to, or null when no size set defines it
     * for this image as it is now. That stops anyone generating sizes the site
     * hasn't defined, and treats requests for out-of-date hashes as missing.
     */
    public function locate(SourceImage $source, ParsedVariantPath $requested, SizeSetCatalogue $catalogue): ?VariantFile
    {
        if ($requested->assetUid !== $source->uid || $requested->basename !== $source->basename()) {
            return null;
        }

        foreach ($this->filesForAll($source, $catalogue) as $file) {
            if ($file->hash === $requested->hash && $file->variant->format === $requested->format) {
                return $file;
            }
        }

        return null;
    }
}
