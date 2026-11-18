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
 * to copy across, and a `<noscript>` copy with real ones follows. An asset's
 * placeholder colour fills the image's box until it loads; a `style` given in
 * the template's attributes replaces it.
 *
 * @internal
 */
final class PictureMarkup
{
    private const string BLANK_PIXEL = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    /**
     * The original file as a plain `<img>`, preceded by an HTML comment
     * saying why when there is one to give. When the template gave the
     * `<picture>` a class or attributes the image is still wrapped in one, so
     * the page's styles keep working.
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

        $img     = self::image($sources, $options, $loading);
        $wrapper = HtmlAttributes::render($options->picture->all());

        return $comment . ('' === $wrapper ? $img : "<picture{$wrapper}>{$img}</picture>");
    }

    public static function picture(PictureSources $picture, PictureOptions $options, ArtDirection ...$art): string
    {
        $prefix = Loading::Lazysrc === $options->loading ? 'data-lazysrc-' : '';
        $sizes  = $options->sizes ?? $picture->sizes;
        $html   = '<picture' . HtmlAttributes::render($options->picture->all()) . '>';

        foreach ($art as $direction) {
            if (null === $direction->picture) {
                $html .= self::blankSource($direction->media, $prefix);

                continue;
            }

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
            'style'  => null === $picture->colour ? null : "background-color:{$picture->colour}",
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
            'style'               => $real['style'],
        ];

        return (
            $html
                . self::image($deferred, $options, Loading::Lazysrc)
                . '</picture><noscript>'
                . self::image($real, $options, Loading::Lazy)
                . '</noscript>'
        );
    }

    private static function blankSource(string $media, string $prefix): string
    {
        return '<source' . HtmlAttributes::render(['media' => $media, "{$prefix}srcset" => self::BLANK_PIXEL]) . '>';
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
                        'class'         => $options->image->class,
                        'loading'       => Loading::Lazy === $loading ? 'lazy' : null,
                        'fetchpriority' => Loading::Priority === $loading ? 'high' : null,
                        'decoding'      => 'async',
                    ],
                    $options->image->attributes,
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
