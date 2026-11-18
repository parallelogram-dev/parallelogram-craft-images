<?php

namespace parallelogram\images\services;

use Craft;
use craft\elements\Asset;
use parallelogram\images\Plugin;
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
        if (Asset::KIND_IMAGE !== $asset->kind || strtolower((string) $asset->getExtension()) === 'svg') {
            return 0;
        }

        $this->applyThreadLimit();

        $transforms = $this->transformsFor($matrixHandles);
        $settings   = Plugin::getInstance()->getSettings();

        $count     = 0;
        $remaining = $transforms;

        if ($settings->fastGenerate && extension_loaded('imagick')) {
            try {
                $remaining = Plugin::getInstance()->pipeline->process($asset, $transforms);
                $count     += count($transforms) - count($remaining);
            } catch (Throwable $e) {
                Craft::warning(
                    sprintf(
                        'Parallelogram Images: fast pipeline failed for asset %d, falling back to Craft: %s',
                        $asset->id,
                        $e->getMessage(),
                    ),
                    __METHOD__,
                );
                $remaining = $transforms;
            }
        }

        foreach ($remaining as $transform) {
            try {
                $asset->getUrl($transform, true);
                $count++;
            } catch (Throwable $e) {
                Craft::error(
                    sprintf(
                        'Parallelogram Images: generation failed for asset %d (%s): %s',
                        $asset->id,
                        json_encode($transform),
                        $e->getMessage(),
                    ),
                    __METHOD__,
                );
            }
        }

        return $count;
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
        $settings     = Plugin::getInstance()->getSettings();
        $supportsAvif = Craft::$app->getImages()->getIsImagick();

        $unique = [];

        foreach (array_unique($matrixHandles) as $handle) {
            $matrix = $settings->matrices[$handle] ?? null;
            if (null === $matrix) {
                continue;
            }

            $formats = $matrix['formats'] ?? $settings->defaults['formats'] ?? [];
            $mode    = $matrix['mode'] ?? $settings->defaults['mode'] ?? 'crop';

            foreach ($matrix['variants'] ?? [] as $variant) {
                foreach ($formats as $format) {
                    if ('avif' === $format && ! $supportsAvif) {
                        continue;
                    }

                    $transform = [
                        'mode'    => $variant['mode'] ?? $mode,
                        'format'  => $format,
                        'quality' => (int) ($settings->formatQuality[$format] ?? 80),
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
                Craft::warning(
                    'Parallelogram Images: could not set ImageMagick thread limit: ' . $e->getMessage(),
                    __METHOD__,
                );
            }
        }
    }
}
