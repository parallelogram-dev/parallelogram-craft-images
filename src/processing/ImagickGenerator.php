<?php

declare(strict_types=1);

namespace parallelogram\images\processing;

use Imagick;
use ImagickException;
use Override;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\SourceFileInterface;
use parallelogram\images\delivery\VariantGeneratorInterface;
use parallelogram\images\sizing\Format;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

use function file_get_contents;
use function intdiv;

/**
 * Makes variants with ImageMagick.
 *
 * Each variant is made from the original: decode, auto-orient, convert to
 * sRGB, crop and resize as {@see ResizePlan} says, sharpen, pad for
 * letterboxing, then encode. Only the first frame of animated images is used.
 *
 * @api
 */
final readonly class ImagickGenerator implements VariantGeneratorInterface
{
    public const string SRGB_PROFILE = __DIR__ . '/../resources/icc/sRGB_v4_ICC_preference.icc';

    public function __construct(
        private SourceFileInterface $files,
        private Processing $processing,
    ) {}

    #[Override]
    public function generate(SourceImage $source, VariantFile $file): string
    {
        $image = new Imagick();

        try {
            $image->readImageBlob($this->files->contents($source));
            $image->setIteratorIndex(0);
            $this->prepare($image);
            $this->resize($image, $source, $file);

            return $this->encode($image, $file->variant->format, $file->variant->quality);
        } catch (ImagickException $exception) {
            throw new GenerationFailedException("ImageMagick couldn't make {$file->path}.", previous: $exception);
        } finally {
            $image->clear();
        }
    }

    /**
     * @throws ImagickException
     */
    private function encode(Imagick $image, Format $format, int $quality): string
    {
        if (Format::Jpg === $format) {
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
        }

        $image->setImageFormat(Format::Jpg === $format ? 'jpeg' : $format->value);
        $image->setImageCompressionQuality($quality);
        $image->setCompressionQuality($quality);

        if (Format::Avif === $format) {
            $image->setOption('heic:speed', (string) $this->processing->avifSpeed());
        }

        return $image->getImageBlob();
    }

    /**
     * Applies the site's processing before resizing.
     *
     * @throws ImagickException
     */
    private function prepare(Imagick $image): void
    {
        if ($this->processing->autoOrient) {
            $image->autoOrient();
        }

        if (! $this->processing->convertToSrgb) {
            return;
        }

        if ([] !== $image->getImageProfiles('icc', include_values: false)) {
            $image->profileImage('icc', (string) file_get_contents(self::SRGB_PROFILE));
        }

        if (Imagick::COLORSPACE_SRGB !== $image->getImageColorspace()) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        $image->stripImage();
    }

    /**
     * @throws ImagickException
     */
    private function resize(Imagick $image, SourceImage $source, VariantFile $file): void
    {
        $plan = ResizePlan::for(
            new Dimensions($image->getImageWidth(), $image->getImageHeight()),
            $source->focalPoint,
            $file->variant->box,
        );

        if (null !== $plan->crop) {
            $image->cropImage($plan->crop->width, $plan->crop->height, $plan->crop->x, $plan->crop->y);
            $image->setImagePage(
                width: 0,
                height: 0,
                x: 0,
                y: 0,
            );
        }

        $image->resizeImage($plan->resize->width, $plan->resize->height, Imagick::FILTER_LANCZOS, blur: 1);

        if ($this->processing->sharpenAmount > 0.0) {
            $image->unsharpMaskImage(
                radius: 0,
                sigma: 0.7,
                amount: $this->processing->sharpenAmount,
                threshold: 0,
            );
        }

        if (null === $plan->canvas) {
            return;
        }

        $image->setImageBackgroundColor(Format::Jpg === $file->variant->format ? 'white' : 'transparent');
        $image->extentImage(
            $plan->canvas->width,
            $plan->canvas->height,
            -intdiv($plan->canvas->width - $plan->resize->width, num2: 2),
            -intdiv($plan->canvas->height - $plan->resize->height, num2: 2),
        );
    }
}
