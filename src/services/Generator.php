<?php

namespace parallelogram\imagevariants\services;

use Craft;
use craft\elements\Asset;
use parallelogram\imagevariants\Plugin;
use Throwable;
use yii\base\Component;

/**
 * Generates the transforms declared by a set of matrices, immediately — every
 * transform is written to disk at call time via getUrl(immediately: true), so
 * nothing is queued. Used synchronously on save/replace and by the warm command.
 */
class Generator extends Component
{
    private bool $threadLimitApplied = false;

    /**
     * Immediately generate every transform for the given matrices on an asset.
     * Returns the number of transforms processed.
     *
     * @param string[] $matrixHandles
     */
    public function generate(Asset $asset, array $matrixHandles): int
    {
        if ($asset->kind !== Asset::KIND_IMAGE || strtolower((string)$asset->getExtension()) === 'svg') {
            return 0;
        }

        $this->applyThreadLimit();

        $count = 0;

        foreach ($this->transformsFor($matrixHandles) as $transform) {
            try {
                $asset->getUrl($transform, true);
                $count++;
            } catch (Throwable $e) {
                Craft::error(
                    sprintf(
                        'Image Variants: generation failed for asset %d (%s): %s',
                        $asset->id,
                        json_encode($transform),
                        $e->getMessage()
                    ),
                    __METHOD__
                );
            }
        }

        return $count;
    }

    /**
     * Set ImageMagick's per-encode thread limit once per process, so warming
     * uses multiple cores within one process (no extra processes spawned).
     */
    private function applyThreadLimit(): void
    {
        if ($this->threadLimitApplied) {
            return;
        }
        $this->threadLimitApplied = true;

        $limit = Plugin::getInstance()->getSettings()->threadLimit;

        if ($limit > 0 && extension_loaded('imagick')) {
            try {
                (new \Imagick())->setResourceLimit(\Imagick::RESOURCETYPE_THREAD, $limit);
            } catch (Throwable $e) {
                Craft::warning('Image Variants: could not set ImageMagick thread limit: ' . $e->getMessage(), __METHOD__);
            }
        }
    }

    /**
     * Flatten the given matrices into a de-duplicated list of transform
     * definitions (variant × format), pulling per-format quality from settings
     * and skipping AVIF when the driver cannot encode it.
     *
     * @param string[] $matrixHandles
     * @return array<int, array<string, mixed>>
     */
    public function transformsFor(array $matrixHandles): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $supportsAvif = Craft::$app->getImages()->getIsImagick();

        $unique = [];

        foreach (array_unique($matrixHandles) as $handle) {
            $matrix = $settings->matrices[$handle] ?? null;
            if ($matrix === null) {
                continue;
            }

            $formats = $matrix['formats'] ?? $settings->defaults['formats'] ?? [];
            $mode = $matrix['mode'] ?? $settings->defaults['mode'] ?? 'crop';

            foreach ($matrix['variants'] ?? [] as $variant) {
                foreach ($formats as $format) {
                    if ($format === 'avif' && !$supportsAvif) {
                        continue;
                    }

                    $transform = [
                        'mode' => $variant['mode'] ?? $mode,
                        'format' => $format,
                        'quality' => (int)($settings->formatQuality[$format] ?? 80),
                    ];

                    if (isset($variant['width'])) {
                        $transform['width'] = $variant['width'];
                    }

                    if (isset($variant['height'])) {
                        $transform['height'] = $variant['height'];
                    }

                    $unique[md5(json_encode($transform))] = $transform;
                }
            }
        }

        return array_values($unique);
    }
}
