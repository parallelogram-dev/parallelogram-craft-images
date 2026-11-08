<?php

declare(strict_types=1);

namespace parallelogram\imagevariants\services;

use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use craft\services\Assets as AssetsService;

/**
 * Carries an image's variants along when Craft moves or renames its file
 *
 * Variant paths are derived from the asset's folder path and filename, so a move
 * (including the automatic one an Assets field makes when its upload subpath uses
 * `{slug}`) would otherwise leave every rendered variant URL pointing at nothing.
 * Craft deletes the transforms it has indexed while relocating the file, so the
 * copy has to happen beforehand, in {@see copyToNewLocation()}; once the save has
 * finished, {@see finishRelocation()} removes the old copies and reports the
 * matrices to generate, so the caller can do that outside the save.
 */
class VariantRelocator
{
    /** @var array<int, array{matrixHandles: string[], oldUris: list<string>}> */
    private array $pending = [];

    public function __construct(
        private readonly PathBuilder $paths,
        private readonly Generator $generator,
        private readonly Warmer $warmer,
        private readonly AssetsService $assets,
    ) {
    }

    /**
     * Returns the number of variant files copied to the asset's pending new location.
     */
    public function copyToNewLocation(Asset $asset, bool $isNew): int
    {
        if ($isNew || $asset->id === null || $asset->kind !== Asset::KIND_IMAGE || $asset->newLocation === null) {
            return 0;
        }

        [$folderId, $filename] = AssetsHelper::parseFileLocation($asset->newLocation);
        $folder                = $this->assets->getFolderById($folderId);

        if ($folder === null || $folder->volumeId !== $asset->getVolumeId()) {
            return 0;
        }

        $matrixHandles = $this->warmer->matrixHandlesForVolume($asset);

        if ($matrixHandles === []) {
            return 0;
        }

        $fs      = $asset->getVolume()->getTransformFs();
        $oldUris = [];
        $copied  = 0;

        foreach ($this->generator->transformsFor($matrixHandles) as $transform) {
            $oldUri = $this->paths->uriFor($asset, $transform);
            $newUri = $this->paths->uriAt($asset, $transform, (string) $folder->path, $filename);

            if ($oldUri === $newUri || ! $fs->fileExists($oldUri)) {
                continue;
            }

            $oldUris[] = $oldUri;

            if (! $fs->fileExists($newUri)) {
                $fs->copyFile($oldUri, $newUri);
                $copied++;
            }
        }

        $this->pending[$asset->id] = ['matrixHandles' => $matrixHandles, 'oldUris' => $oldUris];

        return $copied;
    }

    /**
     * Returns the matrix handles whose missing variants still need generating,
     * or an empty list when the asset had no relocation pending.
     *
     * @return string[]
     */
    public function finishRelocation(Asset $asset): array
    {
        if ($asset->id === null || ! isset($this->pending[$asset->id])) {
            return [];
        }

        ['matrixHandles' => $matrixHandles, 'oldUris' => $oldUris] = $this->pending[$asset->id];
        unset($this->pending[$asset->id]);

        $fs = $asset->getVolume()->getTransformFs();

        foreach ($oldUris as $oldUri) {
            if ($fs->fileExists($oldUri)) {
                $fs->deleteFile($oldUri);
            }
        }

        return $matrixHandles;
    }
}
