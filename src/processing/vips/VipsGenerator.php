<?php

declare(strict_types=1);

namespace parallelogram\images\processing\vips;

use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image;
use Override;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\SourceFileInterface;
use parallelogram\images\delivery\VariantGeneratorInterface;
use parallelogram\images\processing\Backdrop;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\sizing\Format;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

/**
 * Makes variants with libvips through php-vips (PHP's FFI extension): the
 * fastest and least memory-hungry local backend.
 *
 * It follows the same {@see ResizePlan} as the other generators: decode the
 * first frame, auto-orient, convert to sRGB (through an embedded ICC profile
 * when there is one), crop, resize with Lanczos, sharpen, pad, then encode
 * with metadata stripped when converting to sRGB (and kept, profile
 * included, when not).
 *
 * @api
 */
final readonly class VipsGenerator implements VariantGeneratorInterface
{
    private const array WHITE = [255, 255, 255];

    public function __construct(
        private SourceFileInterface $files,
        private Processing $processing,
    ) {}

    #[Override]
    public function generate(SourceImage $source, VariantFile $file): string
    {
        $bytes = $this->files->contents($source);

        try {
            $image = $this->prepare(Image::newFromBuffer($bytes));
            $plan  = ResizePlan::for(
                new Dimensions($image->width, $image->height),
                $source->focalPoint,
                $file->variant->box,
            );

            $rendered = VipsCanvas::render(
                $image,
                $plan,
                Format::Jpg === $file->variant->format ? Backdrop::White : Backdrop::Transparent,
                $this->processing->sharpenAmount,
            );

            return $this->encode($rendered, $file->variant->format, $file->variant->quality);
        } catch (Exception $exception) {
            throw new GenerationFailedException("libvips couldn't make {$file->path}.", previous: $exception);
        }
    }

    /**
     * @throws Exception
     */
    private function encode(Image $image, Format $format, int $quality): string
    {
        $strip = $this->processing->convertToSrgb ? ['keep' => 'none'] : [];

        if (Format::Jpg === $format) {
            $flat = $image->hasAlpha() ? $image->flatten(['background' => self::WHITE]) : $image;

            return $flat->writeToBuffer('.jpg', ['Q' => $quality, 'interlace' => true] + $strip);
        }

        if (Format::Png === $format) {
            return $image->writeToBuffer('.png', ['compression' => 6] + $strip);
        }

        return $image->writeToBuffer(".{$format->value}", ['Q' => $quality] + $strip);
    }

    /**
     * Applies the site's processing before resizing.
     *
     * @throws Exception
     */
    private function prepare(Image $image): Image
    {
        if ($this->processing->autoOrient) {
            $image = $image->autorot();
        }

        if (! $this->processing->convertToSrgb) {
            return $image;
        }

        if (0 !== $image->typeof('icc-profile-data')) {
            return $image->icc_transform('srgb', ['embedded' => true]);
        }

        return 'srgb' === $image->interpretation ? $image : $image->colourspace('srgb');
    }
}
