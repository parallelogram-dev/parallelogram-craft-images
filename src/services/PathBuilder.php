<?php

namespace parallelogram\imagevariants\services;

use craft\elements\Asset;
use craft\helpers\ImageTransforms;
use craft\models\ImageTransform;
use yii\base\Component;

/**
 * Computes the deterministic final URL (and on-disk existence) of a transform
 * WITHOUT generating it or touching the queue. Render reads from here; the
 * warmer writes via the same scheme, so the two cannot disagree.
 *
 * Mirrors craft\imagetransforms\ImageTransformer: the URI is
 *   {volume transformSubpath}{asset folderPath}{transformString}[/{assetId}]/{filename}
 * where the {assetId} subfolder is present only when the transform's filename
 * differs from the source filename (i.e. format-converted variants).
 */
class PathBuilder extends Component
{
    /**
     * Final URL for a transform, or null if the volume's transform filesystem
     * has no public URLs. Never generates, never queues.
     *
     * @param array<string, mixed>|ImageTransform $transform
     */
    public function urlFor(Asset $asset, array|ImageTransform $transform): ?string
    {
        $fs = $asset->getVolume()->getTransformFs();
        if (!$fs->hasUrls) {
            return null;
        }

        return rtrim((string)$fs->getRootUrl(), '/') . '/' . ltrim($this->uriFor($asset, $transform), '/');
    }

    /**
     * Whether the transform file already exists on disk.
     *
     * @param array<string, mixed>|ImageTransform $transform
     */
    public function exists(Asset $asset, array|ImageTransform $transform): bool
    {
        return $asset->getVolume()->getTransformFs()->fileExists($this->uriFor($asset, $transform));
    }

    /**
     * Whether the transform file exists AND is non-empty — so a truncated or
     * failed transform reads as missing rather than falsely passing. Falls back
     * to plain existence on filesystems that don't expose a size.
     *
     * @param array<string, mixed>|ImageTransform $transform
     */
    public function isUsable(Asset $asset, array|ImageTransform $transform): bool
    {
        $fs = $asset->getVolume()->getTransformFs();
        $uri = $this->uriFor($asset, $transform);

        if (!$fs->fileExists($uri)) {
            return false;
        }

        if (method_exists($fs, 'getFileSize')) {
            try {
                return $fs->getFileSize($uri) > 0;
            } catch (\Throwable) {
                return true;
            }
        }

        return true;
    }

    /**
     * The transform URI relative to the transform filesystem root.
     *
     * @param array<string, mixed>|ImageTransform $transform
     */
    public function uriFor(Asset $asset, array|ImageTransform $transform): string
    {
        return $this->uriAt($asset, $transform, (string)$asset->folderPath, $asset->getFilename());
    }

    /**
     * The transform URI the asset would have if its file lived at
     * $folderPath/$sourceFilename, so a pending move or rename can be mapped
     * before Craft relocates the file.
     *
     * @param array<string, mixed>|ImageTransform $transform
     */
    public function uriAt(Asset $asset, array|ImageTransform $transform, string $folderPath, string $sourceFilename): string
    {
        $imageTransform = $transform instanceof ImageTransform
            ? $transform
            : ImageTransforms::normalizeTransform($transform);

        $transformString = ImageTransforms::getTransformString($imageTransform);

        $basename = pathinfo($sourceFilename, PATHINFO_FILENAME);
        $extension = $imageTransform->format ?: pathinfo($sourceFilename, PATHINFO_EXTENSION);
        $filename = $basename . '.' . $extension;

        $subfolder = $transformString;
        if ($filename !== $sourceFilename) {
            $subfolder .= '/' . $asset->id;
        }

        return $this->basePath($asset, $folderPath) . $subfolder . '/' . $filename;
    }

    private function basePath(Asset $asset, string $folderPath): string
    {
        $subpath = rtrim($asset->getVolume()->getTransformSubpath(), '/');
        $base = ($subpath !== '' ? $subpath . '/' : '') . $folderPath;

        return str_replace('\\', '/', $base);
    }
}
