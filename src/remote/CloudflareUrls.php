<?php

declare(strict_types=1);

namespace parallelogram\images\remote;

use Override;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;

use function implode;
use function rtrim;
use function sprintf;

/**
 * Cloudflare Images "transform via URL" links:
 * `{zone}/cdn-cgi/image/{options}/{original}`.
 *
 * - No height: `fit=scale-down` (never enlarges)
 * - Crop: `fit=crop` around the focal point (`gravity=XxY`, never enlarges)
 * - Fit: `fit=scale-down` (never enlarges)
 * - Letterbox: `fit=pad`, padded with white
 * - Stretch: `fit=squeeze`
 *
 * Cloudflare differs from the other backends in three ways: it can't output
 * PNG (asking for it is an error), letterboxing and stretching may enlarge an
 * image that's smaller than the box, and padding is always white, never
 * transparent. Cloudflare also decides orientation and colour conversion
 * itself; only sharpening follows the site's processing settings.
 *
 * @api
 */
final readonly class CloudflareUrls implements VariantUrlServiceInterface
{
    public function __construct(
        private string $zoneUrl,
        private Processing $processing,
    ) {}

    /**
     * @throws InvalidVariantException When the format is PNG.
     */
    private static function format(Format $format): string
    {
        if (Format::Png === $format) {
            throw new InvalidVariantException(
                "Cloudflare Images can't make PNG files. Use AVIF, WebP or JPG for this volume.",
            );
        }

        return Format::Jpg === $format ? 'jpeg' : $format->value;
    }

    /**
     * @return list<string>
     */
    private static function sizing(Box $box, FocalPoint $focalPoint): array
    {
        if (null === $box->height) {
            return ["width={$box->width}", 'fit=scale-down'];
        }

        $size = ["width={$box->width}", "height={$box->height}"];

        if (Mode::Crop === $box->mode) {
            return [...$size, 'fit=crop', sprintf('gravity=%.4Fx%.4F', $focalPoint->x, $focalPoint->y)];
        }

        if (Mode::Fit === $box->mode) {
            return [...$size, 'fit=scale-down'];
        }

        return [...$size, Mode::Letterbox === $box->mode ? 'fit=pad' : 'fit=squeeze'];
    }

    #[Override]
    public function urlFor(SourceImage $source, Variant $variant, string $sourceUrl): string
    {
        $options = [
            ...self::sizing($variant->box, $source->focalPoint),
            "quality={$variant->quality}",
            'format=' . self::format($variant->format),
            'metadata=none',
            'anim=false',
        ];

        if ($this->processing->sharpenAmount > 0.0) {
            $options[] = sprintf('sharpen=%.2F', $this->processing->sharpenAmount);
        }

        return rtrim($this->zoneUrl, characters: '/') . '/cdn-cgi/image/' . implode(',', $options) . "/{$sourceUrl}";
    }
}
