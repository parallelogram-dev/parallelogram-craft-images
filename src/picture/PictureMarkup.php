<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use function array_replace;
use function str_replace;

/**
 * Writes the HTML for `asset.picture()`: a `<picture>` with a `<source>` per
 * modern format and an `<img>` in the fallback format, or a plain `<img>` of
 * the original when there are no variants.
 *
 * With Lazysrc, sources go in `data-lazysrc-*` attributes for ParallelogramJS
 * to copy across, and a `<noscript>` copy with real ones follows.
 *
 * @internal
 */
final class PictureMarkup
{
    /**
     * The original file as a plain `<img>`, preceded by an HTML comment
     * saying why when there is one to give.
     */
    public static function original(OriginalImage $image, PictureOptions $options, ?string $note = null): string
    {
        $comment = null === $note
            ? ''
            : '<!-- '
            . str_replace(
                search: '--',
                replace: '- -',
                subject: $note,
            )
            . ' -->';
        $loading = Loading::Priority === $options->loading ? Loading::Priority : Loading::Lazy;
        $sources = ['src' => $image->url, 'width' => $image->width, 'height' => $image->height];

        return $comment . self::image($sources, $options, $loading);
    }

    public static function picture(PictureSources $picture, PictureOptions $options, ArtDirection ...$art): string
    {
        $prefix = Loading::Lazysrc === $options->loading ? 'data-lazysrc-' : '';
        $sizes  = $options->sizes ?? $picture->sizes;
        $html   = '<picture>';

        foreach ($art as $direction) {
            foreach ($direction->picture->sources as $source) {
                $html .= self::source(
                    $source,
                    $direction->picture,
                    $direction->picture->sizes,
                    $prefix,
                    $direction->media,
                );
            }
        }

        foreach ($picture->sources as $source) {
            if ($source->format === $picture->fallback->format) {
                continue;
            }

            $html .= self::source($source, $picture, $sizes, $prefix);
        }

        $real = [
            'src'    => $picture->fallback->url,
            'srcset' => $picture->fallback->srcset,
            'sizes'  => $sizes,
            'width'  => $picture->size->width,
            'height' => $picture->size->height,
        ];

        if (Loading::Lazysrc !== $options->loading) {
            return $html . self::image($real, $options, $options->loading) . '</picture>';
        }

        $deferred = [
            'data-lazysrc'        => true,
            'data-lazysrc-src'    => $real['src'],
            'data-lazysrc-srcset' => $real['srcset'],
            'data-lazysrc-sizes'  => $real['sizes'],
            'width'               => $real['width'],
            'height'              => $real['height'],
        ];

        return (
            $html
                . self::image($deferred, $options, Loading::Lazysrc)
                . '</picture><noscript>'
                . self::image($real, $options, Loading::Lazy)
                . '</noscript>'
        );
    }

    /**
     * @param array<string, scalar|null> $sources
     */
    private static function image(array $sources, PictureOptions $options, Loading $loading): string
    {
        return (
            '<img'
                . HtmlAttributes::render(array_replace(
                    $sources,
                    [
                        'alt'           => $options->alt,
                        'class'         => $options->class,
                        'loading'       => Loading::Lazy === $loading ? 'lazy' : null,
                        'fetchpriority' => Loading::Priority === $loading ? 'high' : null,
                        'decoding'      => 'async',
                    ],
                    $options->attributes,
                ))
                . '>'
        );
    }

    private static function source(
        FormatSource $source,
        PictureSources $picture,
        string $sizes,
        string $prefix,
        ?string $media = null,
    ): string {
        return (
            '<source'
                . HtmlAttributes::render([
                    'media'           => $media,
                    'type'            => $source->mimeType,
                    "{$prefix}srcset" => $source->srcset,
                    "{$prefix}sizes"  => $sizes,
                    'width'           => $picture->size->width,
                    'height'          => $picture->size->height,
                ])
                . '>'
        );
    }
}
