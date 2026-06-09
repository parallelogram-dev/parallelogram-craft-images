<?php

namespace parallelogram\imagevariants\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use Imagick;
use parallelogram\imagevariants\Plugin;
use Throwable;
use yii\base\Component;

/**
 * The fast generation path: decode the source once, then produce every requested
 * variant from that single in-memory image — resizing each output directly from
 * the full-resolution decode (B1), rather than paying a fresh Craft transform
 * (and a fresh decode) per output.
 *
 * It deliberately differs from Craft's transformer in three ways, by request:
 *   - no EXIF auto-orientation (pixels are resized as they sit in the file);
 *   - the source is converted to sRGB once, so wide-gamut originals render
 *     predictably on the web;
 *   - a gentle unsharp mask is applied per resized geometry to recover the
 *     softness a downscale introduces.
 *
 * Output is written to the exact URI {@see PathBuilder::uriFor()} computes, so
 * render and audit read from the same place the slow path would have written.
 * Geometries it does not handle (a crop/letterbox/stretch with both dimensions),
 * and any output it fails to write, are returned to the caller to fall back to
 * Craft — so the fast path can never silently emit a wrong or missing file.
 */
class Pipeline extends Component
{
    private const SRGB_PROFILE = __DIR__ . '/../resources/icc/sRGB-IEC61966-2.1.icc';

    /**
     * Generate every transform it can for one asset from a single decode.
     *
     * @param array<int, array<string, mixed>> $transforms expanded variant×format list
     * @return array<int, array<string, mixed>> the transforms it did NOT write (caller must fall back)
     */
    public function process(Asset $asset, array $transforms): array
    {
        if ($transforms === []) {
            return [];
        }

        $settings = Plugin::getInstance()->getSettings();
        $paths = Plugin::getInstance()->paths;

        // Centre-crop only when the asset has no custom focal point; otherwise
        // defer crops to Craft, which crops around the focal point.
        $allowCrop = !$asset->getHasFocalPoint();

        [$supported, $unhandled] = $this->partition($transforms, $allowCrop);

        // Idempotent, like the slow path: leave outputs that already exist and
        // are usable. rebuild() deletes first when a true refresh is wanted.
        $supported = array_values(array_filter(
            $supported,
            static fn(array $transform): bool => !$paths->isUsable($asset, $transform)
        ));

        if ($supported === []) {
            return $unhandled;
        }

        $local = $asset->getCopyOfFile();

        $source = new Imagick();
        try {
            $source->readImage($local);

            if ($settings->convertToSrgb) {
                $this->toSrgb($source);
            }

            [$sourceWidth, $sourceHeight] = [$source->getImageWidth(), $source->getImageHeight()];
            $upscale = Craft::$app->getConfig()->getGeneral()->upscaleImages;

            foreach ($this->groupByGeometry($supported) as $group) {
                $unhandled = array_merge(
                    $unhandled,
                    $this->renderGeometry($asset, $source, $group, $sourceWidth, $sourceHeight, $upscale, (float)$settings->sharpenAmount)
                );
            }
        } finally {
            $source->clear();
            if (is_string($local) && is_file($local)) {
                FileHelper::unlink($local);
            }
        }

        return $unhandled;
    }

    /**
     * Split transforms into those the fast path can produce and those it must
     * defer to Craft. Handled: a proportional scale (width-only, height-only, or
     * width+height "fit"), and — when $allowCrop — a width+height centre "crop".
     * Deferred: stretch/letterbox, and crop when a focal point is in play.
     *
     * @param array<int, array<string, mixed>> $transforms
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function partition(array $transforms, bool $allowCrop): array
    {
        $supported = [];
        $unhandled = [];

        foreach ($transforms as $transform) {
            $hasWidth = !empty($transform['width']);
            $hasHeight = !empty($transform['height']);
            $mode = $transform['mode'] ?? 'crop';

            $isScale = ($hasWidth xor $hasHeight) || ($hasWidth && $hasHeight && $mode === 'fit');
            $isCrop = $hasWidth && $hasHeight && $mode === 'crop' && $allowCrop;

            if ($isScale || $isCrop) {
                $supported[] = $transform;
            } else {
                $unhandled[] = $transform;
            }
        }

        return [$supported, $unhandled];
    }

    /**
     * Group supported transforms by their geometry, so a width shared across
     * formats is resized once and encoded to each format from that single buffer.
     *
     * @param array<int, array<string, mixed>> $transforms
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByGeometry(array $transforms): array
    {
        $groups = [];

        foreach ($transforms as $transform) {
            $key = ($transform['width'] ?? '') . 'x' . ($transform['height'] ?? '') . '/' . ($transform['mode'] ?? 'crop');
            $groups[$key][] = $transform;
        }

        return $groups;
    }

    /**
     * Resize the source to one geometry, sharpen once, then encode and write each
     * format. Returns any transforms in the group it failed to write.
     *
     * @param array<int, array<string, mixed>> $group transforms sharing a geometry
     * @return array<int, array<string, mixed>> failures to fall back on
     */
    private function renderGeometry(
        Asset $asset,
        Imagick $source,
        array $group,
        int $sourceWidth,
        int $sourceHeight,
        bool $upscale,
        float $sharpenAmount
    ): array {
        $resized = clone $source;
        try {
            $this->applyGeometry($resized, $group[0], $sourceWidth, $sourceHeight, $upscale);

            if ($sharpenAmount > 0) {
                $resized->unsharpMaskImage(0, 0.7, $sharpenAmount, 0);
            }

            $failures = [];

            foreach ($group as $transform) {
                if (!$this->encodeAndWrite($asset, $resized, $transform)) {
                    $failures[] = $transform;
                }
            }

            return $failures;
        } finally {
            $resized->clear();
        }
    }

    /**
     * Resize (and, for a width+height crop, centre-crop) the image in place to
     * the transform's geometry.
     *
     * @param array<string, mixed> $transform
     */
    private function applyGeometry(Imagick $image, array $transform, int $sourceWidth, int $sourceHeight, bool $upscale): void
    {
        $hasWidth = !empty($transform['width']);
        $hasHeight = !empty($transform['height']);
        $mode = $transform['mode'] ?? 'crop';

        if ($hasWidth && $hasHeight && $mode === 'crop') {
            $this->coverCrop($image, (int)$transform['width'], (int)$transform['height'], $sourceWidth, $sourceHeight, $upscale);
            return;
        }

        [$width, $height] = $this->targetDimensions($transform, $sourceWidth, $sourceHeight, $upscale);
        $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
    }

    /**
     * Scale to cover the target box (Lanczos) then centre-crop to it — matching
     * Craft's center-center crop and CSS object-fit: cover. Honours
     * upscaleImages: when off, never enlarges past the source.
     */
    private function coverCrop(Imagick $image, int $targetW, int $targetH, int $sourceWidth, int $sourceHeight, bool $upscale): void
    {
        $scale = max($targetW / $sourceWidth, $targetH / $sourceHeight);
        if (!$upscale) {
            $scale = min($scale, 1.0);
        }

        $scaledW = max(1, (int)ceil($sourceWidth * $scale));
        $scaledH = max(1, (int)ceil($sourceHeight * $scale));
        $image->resizeImage($scaledW, $scaledH, Imagick::FILTER_LANCZOS, 1);

        // With upscaling off, the source may not cover the box; clamp the crop
        // to what's available so it stays in bounds.
        $cropW = min($targetW, $scaledW);
        $cropH = min($targetH, $scaledH);
        $x = (int)max(0, round(($scaledW - $cropW) / 2));
        $y = (int)max(0, round(($scaledH - $cropH) / 2));

        $image->cropImage($cropW, $cropH, $x, $y);
        $image->setImagePage($cropW, $cropH, 0, 0);
    }

    /**
     * The output pixel dimensions for a proportional scale, honouring
     * upscaleImages (when off, never enlarge past the source).
     *
     * @param array<string, mixed> $transform
     * @return array{0: int, 1: int}
     */
    private function targetDimensions(array $transform, int $sourceWidth, int $sourceHeight, bool $upscale): array
    {
        $width = !empty($transform['width']) ? (int)$transform['width'] : null;
        $height = !empty($transform['height']) ? (int)$transform['height'] : null;

        if ($width !== null && $height !== null) {
            $scale = min($width / $sourceWidth, $height / $sourceHeight);
        } elseif ($width !== null) {
            $scale = $width / $sourceWidth;
        } else {
            $scale = $height / $sourceHeight;
        }

        if (!$upscale) {
            $scale = min($scale, 1.0);
        }

        return [
            max(1, (int)round($sourceWidth * $scale)),
            max(1, (int)round($sourceHeight * $scale)),
        ];
    }

    /**
     * Encode the resized buffer to one format and write it to its deterministic
     * URI. Returns false on any failure so the caller can fall back to Craft.
     *
     * @param array<string, mixed> $transform
     */
    private function encodeAndWrite(Asset $asset, Imagick $resized, array $transform): bool
    {
        try {
            $frame = $this->encodeFrame($resized, (string)($transform['format'] ?? 'jpg'), (int)($transform['quality'] ?? 80));

            try {
                $uri = Plugin::getInstance()->paths->uriFor($asset, $transform);
                $asset->getVolume()->getTransformFs()->write($uri, $frame->getImageBlob());
            } finally {
                $frame->clear();
            }

            return true;
        } catch (Throwable $e) {
            Craft::warning(
                sprintf(
                    'Image Variants: fast encode failed for asset %d (%s): %s',
                    $asset->id,
                    json_encode($transform),
                    $e->getMessage()
                ),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * Encode a resized buffer to one format, returning a fresh Imagick the caller
     * owns (and must clear). JPEG, having no alpha, is flattened onto white;
     * metadata is stripped since the pixels are already sRGB.
     */
    private function encodeFrame(Imagick $resized, string $format, int $quality): Imagick
    {
        $frame = clone $resized;

        if (in_array($format, ['jpg', 'jpeg'], true)) {
            $frame->setImageBackgroundColor('white');
            $frame->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $frame = $frame->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }

        $frame->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
        $frame->setImageCompressionQuality($quality);
        $frame->stripImage();

        return $frame;
    }

    /**
     * Produce the fast-path output for a single transform as a blob, WITHOUT
     * writing it anywhere. For the parity gate, which compares this against the
     * file Craft's transformer produces for the same definition.
     *
     * @param array<string, mixed> $transform
     */
    public function previewBlob(Asset $asset, array $transform): ?string
    {
        $settings = Plugin::getInstance()->getSettings();
        $local = $asset->getCopyOfFile();
        $source = new Imagick();

        try {
            $source->readImage($local);

            if ($settings->convertToSrgb) {
                $this->toSrgb($source);
            }

            $resized = clone $source;
            $this->applyGeometry(
                $resized,
                $transform,
                $source->getImageWidth(),
                $source->getImageHeight(),
                Craft::$app->getConfig()->getGeneral()->upscaleImages
            );

            $amount = (float)$settings->sharpenAmount;
            if ($amount > 0) {
                $resized->unsharpMaskImage(0, 0.7, $amount, 0);
            }

            $frame = $this->encodeFrame($resized, (string)($transform['format'] ?? 'jpg'), (int)($transform['quality'] ?? 80));
            $blob = $frame->getImageBlob();
            $frame->clear();
            $resized->clear();

            return $blob;
        } finally {
            $source->clear();
            if (is_string($local) && is_file($local)) {
                FileHelper::unlink($local);
            }
        }
    }

    /**
     * Convert the working image to sRGB. An embedded profile (or a CMYK source)
     * is converted through the bundled sRGB ICC profile; an untagged RGB source
     * is already assumed sRGB and left untouched.
     */
    private function toSrgb(Imagick $image): void
    {
        $profiles = $image->getImageProfiles('icc', true);
        $srgb = (string)file_get_contents(self::SRGB_PROFILE);

        if (!empty($profiles['icc'])) {
            $image->profileImage('icc', $srgb);
            return;
        }

        if ($image->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
            $image->profileImage('icc', $srgb);
        }
    }
}
