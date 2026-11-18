<?php

declare(strict_types=1);

namespace parallelogram\images\remote;

use Override;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;

use function base64_encode;
use function implode;
use function rtrim;
use function sprintf;
use function strtr;

/**
 * Signed imgproxy URLs. Each mode maps onto imgproxy's own resizing, and
 * nothing is ever enlarged:
 *
 * - No height: `rs:fit:{w}:0:0`
 * - Crop: `rs:fill:{w}:{h}:0` around the focal point (`g:fp:{x}:{y}`)
 * - Fit: `rs:fit:{w}:{h}:0`
 * - Letterbox: `rs:fit:{w}:{h}:0:1`, padded with white for JPG
 * - Stretch: `rs:force:{w}:{h}:0`
 *
 * The site's processing settings become `ar` (auto-rotate), `scp` (convert to
 * sRGB) and `sh` (sharpen), and metadata is always stripped. The original's
 * URL is Base64-encoded and the extension sets the output format.
 *
 * @api
 */
final readonly class ImgproxyUrls implements VariantUrlServiceInterface
{
    public function __construct(
        private string $baseUrl,
        private ImgproxySigner $signer,
        private Processing $processing,
    ) {}

    private static function encode(string $sourceUrl): string
    {
        return rtrim(strtr(base64_encode($sourceUrl), from: '+/', to: '-_'), characters: '=');
    }

    /**
     * @return list<string>
     */
    private static function resizing(Box $box, Format $format, FocalPoint $focalPoint): array
    {
        if (null === $box->height) {
            return ["rs:fit:{$box->width}:0:0"];
        }

        $size = "{$box->width}:{$box->height}:0";

        if (Mode::Crop === $box->mode) {
            return ["rs:fill:{$size}", sprintf('g:fp:%.4F:%.4F', $focalPoint->x, $focalPoint->y)];
        }

        if (Mode::Stretch === $box->mode) {
            return ["rs:force:{$size}"];
        }

        if (Mode::Fit === $box->mode) {
            return ["rs:fit:{$size}"];
        }

        return Format::Jpg === $format ? ["rs:fit:{$size}:1", 'bg:255:255:255'] : ["rs:fit:{$size}:1"];
    }

    #[Override]
    public function urlFor(SourceImage $source, Variant $variant, string $sourceUrl): string
    {
        $options = [
            ...self::resizing($variant->box, $variant->format, $source->focalPoint),
            "q:{$variant->quality}",
            ...$this->processingOptions(),
        ];

        $path = '/' . implode('/', $options) . '/' . self::encode($sourceUrl) . ".{$variant->format->value}";

        return rtrim($this->baseUrl, characters: '/') . '/' . $this->signer->sign($path) . $path;
    }

    /**
     * @return list<string>
     */
    private function processingOptions(): array
    {
        $options = [
            'ar:' . ($this->processing->autoOrient ? '1' : '0'),
            'scp:' . ($this->processing->convertToSrgb ? '1' : '0'),
            'sm:1',
        ];

        if ($this->processing->sharpenAmount > 0.0) {
            $options[] = sprintf('sh:%.2F', $this->processing->sharpenAmount);
        }

        return $options;
    }
}
