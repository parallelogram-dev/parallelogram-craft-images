<?php

namespace parallelogram\images\models;

use craft\elements\Asset;
use InvalidArgumentException;
use parallelogram\images\services\PathBuilder;

/**
 * The Twig-facing result of resolving (asset, field, key). Produces per-format
 * srcset strings and URLs of final, pre-generated static paths — it never
 * generates. A missing file therefore 404s, surfacing a warming gap.
 *
 * Mirrored vocabulary to Craft assets is intentional; note `srcset()` takes a
 * format, not (sizes, transform).
 */
class VariantSet
{
    /**
     * @param array<string, mixed> $matrix Resolved matrix definition.
     * @param array<string, int> $formatQuality
     * @param array<string, mixed> $defaults
     * @param string[] $formats Driver-supported formats for this matrix.
     */
    public function __construct(
        private readonly Asset $asset,
        private readonly array $matrix,
        private readonly array $formatQuality,
        private readonly array $defaults,
        private readonly array $formats,
        private readonly PathBuilder $paths,
    ) {}

    /**
     * @return string[]
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    public function getHeight(): ?int
    {
        return $this->asset->getHeight();
    }

    public function getSizes(): string
    {
        return (string) ($this->matrix['sizes'] ?? '');
    }

    public function getWidth(): ?int
    {
        return $this->asset->getWidth();
    }

    public function has(string $format): bool
    {
        return in_array($format, $this->formats, true);
    }

    /**
     * Width-descriptor srcset for one format.
     */
    public function srcset(string $format): string
    {
        $this->assertFormat($format);

        $entries = [];

        foreach ($this->matrix['variants'] ?? [] as $variant) {
            $url        = $this->paths->urlFor($this->asset, $this->transform($variant, $format));
            $descriptor = $variant['width'] ?? $this->asset->getWidth();
            $entries[]  = $url . ' ' . $descriptor . 'w';
        }

        return implode(', ', $entries);
    }

    /**
     * A single URL for one format — the smallest variant by default, or the
     * variant matching $width.
     */
    public function url(string $format, ?int $width = null): ?string
    {
        $this->assertFormat($format);

        $variants = $this->matrix['variants'] ?? [];
        if ([] === $variants) {
            return null;
        }

        $chosen = $variants[0];
        if (null !== $width) {
            foreach ($variants as $variant) {
                if (($variant['width'] ?? null) === $width) {
                    $chosen = $variant;
                    break;
                }
            }
        }

        return $this->paths->urlFor($this->asset, $this->transform($chosen, $format));
    }

    private function assertFormat(string $format): void
    {
        if (! $this->has($format)) {
            throw new InvalidArgumentException("Format “{$format}” is not available in this matrix.");
        }
    }

    /**
     * @param array<string, mixed> $variant
     * @return array<string, mixed>
     */
    private function transform(array $variant, string $format): array
    {
        $transform = [
            'mode'    => $variant['mode'] ?? $this->matrix['mode'] ?? $this->defaults['mode'] ?? 'crop',
            'format'  => $format,
            'quality' => (int) ($this->formatQuality[$format] ?? 80),
        ];

        if (isset($variant['width'])) {
            $transform['width'] = $variant['width'];
        }

        if (isset($variant['height'])) {
            $transform['height'] = $variant['height'];
        }

        return $transform;
    }
}
