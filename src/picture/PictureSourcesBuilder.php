<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use parallelogram\images\processing\ResizePlan;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

use function implode;

/**
 * Turns the files a size set needs for an image into `srcset` values. Each
 * width descriptor is the width the file really has, which can be narrower
 * than its box when an image is fitted inside one.
 *
 * @internal
 */
final class PictureSourcesBuilder
{
    /**
     * @param list<VariantFile> $files The planner's files for this image and size set, ascending by width.
     * @param string|null $colour The asset's placeholder colour, if it has one.
     *
     * @throws InvalidVariantException When there are no files in the fallback format, or a link can't be made.
     */
    public static function build(
        SourceImage $source,
        SizeSet $sizeSet,
        array $files,
        VariantLinksInterface $links,
        ?string $colour = null,
    ): PictureSources {
        $byFormat = [];

        foreach ($files as $file) {
            $plan = ResizePlan::for($source->dimensions, $source->focalPoint, $file->variant->box);

            $byFormat[$file->variant->format->value][] = [$links->urlFor($file), $plan->canvas ?? $plan->resize];
        }

        $sources  = [];
        $fallback = null;

        foreach ($sizeSet->encoding->formats as $format) {
            $candidates = [];
            $widest     = null;

            foreach ($byFormat[$format->value] ?? [] as [$url, $output]) {
                $candidates[] = "{$url} {$output->width}w";
                $widest       = [$url, $output];
            }

            if (null === $widest) {
                continue;
            }

            $formatSource = new FormatSource($format, implode(', ', $candidates), $widest[0]);

            if ($format === $sizeSet->encoding->fallback) {
                $fallback = [$formatSource, $widest[1]];

                continue;
            }

            $sources[] = $formatSource;
        }

        if (null === $fallback) {
            throw new InvalidVariantException(
                "“{$sizeSet->handle}” has no {$sizeSet->encoding->fallback->value} files for this image.",
            );
        }

        return new PictureSources(
            $sizeSet->handle,
            [...$sources, $fallback[0]],
            $sizeSet->sizes,
            $fallback[1],
            $colour,
        );
    }
}
