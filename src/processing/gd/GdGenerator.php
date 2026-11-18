<?php

declare(strict_types=1);

namespace parallelogram\images\processing\gd;

use GdImage;
use Override;
use parallelogram\images\delivery\SourceFileInterface;
use parallelogram\images\delivery\VariantGeneratorInterface;
use parallelogram\images\processing\Backdrop;
use parallelogram\images\processing\ResizePlan;
use parallelogram\images\sizing\Format;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

use function imagecreatefromstring;
use function imageistruecolor;
use function imagepalettetotruecolor;
use function imagesx;
use function imagesy;

/**
 * Makes variants with PHP's GD extension, for servers without ImageMagick.
 *
 * It follows the same {@see ResizePlan} as the ImageMagick generator and
 * applies EXIF orientation and sharpening, but GD can't convert colour
 * profiles, so the "convert to sRGB" setting has no effect here.
 *
 * @api
 */
final readonly class GdGenerator implements VariantGeneratorInterface
{
    public function __construct(
        private SourceFileInterface $files,
        private Processing $processing,
    ) {}

    #[Override]
    public function generate(SourceImage $source, VariantFile $file): string
    {
        $bytes = $this->files->contents($source);
        $image = GdGuard::call('read the original', static fn(): GdImage|false => imagecreatefromstring($bytes));

        if (! imageistruecolor($image)) {
            GdGuard::call('convert the palette', static fn(): bool => imagepalettetotruecolor($image));
        }

        if ($this->processing->autoOrient) {
            $image = GdOrientation::apply($image, GdOrientation::read($bytes));
        }

        $plan = ResizePlan::for(
            new Dimensions(imagesx($image), imagesy($image)),
            $source->focalPoint,
            $file->variant->box,
        );

        $rendered = GdCanvas::render(
            $image,
            $plan,
            Format::Jpg === $file->variant->format ? Backdrop::White : Backdrop::Transparent,
            $this->processing->sharpenAmount,
        );

        return GdEncoder::encode($rendered, $file->variant->format, $file->variant->quality);
    }
}
