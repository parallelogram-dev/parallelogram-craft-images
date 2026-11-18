<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\picture;

use parallelogram\images\picture\ArtDirection;
use parallelogram\images\picture\ElementAttributes;
use parallelogram\images\picture\FormatSource;
use parallelogram\images\picture\HtmlAttributes;
use parallelogram\images\picture\Loading;
use parallelogram\images\picture\OriginalImage;
use parallelogram\images\picture\PictureMarkup;
use parallelogram\images\picture\PictureOptions;
use parallelogram\images\picture\PictureSources;
use parallelogram\images\sizing\Format;
use parallelogram\images\test\TestAsset\Factory\PictureSourcesFactory;
use parallelogram\images\variants\Dimensions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function str_contains;
use function str_starts_with;
use function substr_count;

/**
 * @internal
 */
#[CoversClass(PictureMarkup::class)]
#[CoversClass(HtmlAttributes::class)]
#[CoversClass(ElementAttributes::class)]
#[CoversClass(ArtDirection::class)]
#[CoversClass(OriginalImage::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Format::class)]
#[UsesClass(FormatSource::class)]
#[UsesClass(PictureOptions::class)]
#[UsesClass(PictureSources::class)]
#[Group('unit')]
final class PictureMarkupTest extends TestCase
{
    private const string AVIF = '<source type="image/avif" srcset="/card-480.avif 480w, /card-960.avif 960w" sizes="100vw" width="960" height="720">';

    private const string IMG = 'src="/card-960.jpg" srcset="/card-480.jpg 480w, /card-960.jpg 960w" sizes="100vw" width="960" height="720" alt="Harbour"';

    #[Test]
    public function escapesAttributeValues(): void
    {
        $html = PictureMarkup::original(
            new OriginalImage('/a.jpg?x=1&y="2"', null, null),
            new PictureOptions('Tom & Jerry\'s <boat>'),
        );

        static::assertSame(
            '<img src="/a.jpg?x=1&amp;y=&quot;2&quot;" alt="Tom &amp; Jerry&apos;s &lt;boat&gt;" loading="lazy" decoding="async">',
            $html,
        );
    }

    #[Test]
    public function explainsAnOriginalInAComment(): void
    {
        $html = PictureMarkup::original(
            new OriginalImage('/a.jpg', 800, 600),
            new PictureOptions('A', loading: Loading::Lazysrc),
            note: 'Variants aren--t set up',
        );

        static::assertSame(
            '<!-- Variants aren- -t set up --><img src="/a.jpg" width="800" height="600" alt="A" loading="lazy" decoding="async">',
            $html,
        );
    }

    #[Test]
    public function fillsThePictureWithItsPlaceholderColourUntilTheImageLoads(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(colour: '#7a8b6c'),
            new PictureOptions('Harbour', loading: Loading::Lazysrc),
        );

        static::assertSame(
            [true, 1],
            [
                str_starts_with($html, '<picture style="background-color:#7a8b6c">'),
                substr_count($html, needle: 'style='),
            ],
        );
    }

    #[Test]
    public function handsSourcesToLazysrcWithANoscriptCopy(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(),
            new PictureOptions('Harbour', loading: Loading::Lazysrc),
        );

        static::assertSame(
            '<picture><source type="image/avif" data-lazysrc-srcset="/card-480.avif 480w, /card-960.avif 960w" data-lazysrc-sizes="100vw" width="960" height="720">'
                . '<img data-lazysrc data-lazysrc-src="/card-960.jpg" data-lazysrc-srcset="/card-480.jpg 480w, /card-960.jpg 960w" data-lazysrc-sizes="100vw" width="960" height="720" alt="Harbour" decoding="async"></picture>'
                . '<noscript><img '
                . self::IMG
                . ' loading="lazy" decoding="async"></noscript>',
            $html,
        );
    }

    #[Test]
    public function keepsTheOriginalInsideItsPictureWhenTheTemplateStylesOne(): void
    {
        $html = PictureMarkup::original(
            new OriginalImage('/a.jpg', 800, 600),
            new PictureOptions('A', picture: new ElementAttributes('frame')),
        );

        static::assertSame(
            '<picture class="frame"><img src="/a.jpg" width="800" height="600" alt="A" loading="lazy" decoding="async"></picture>',
            $html,
        );
    }

    #[Test]
    public function leavesTheImagesOwnStyleAloneBesideThePlaceholderColour(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(colour: '#7a8b6c'),
            new PictureOptions('Harbour', image: new ElementAttributes(attributes: ['style' => 'object-fit:cover'])),
        );

        static::assertSame(
            [true, 1],
            [
                str_starts_with($html, '<picture style="background-color:#7a8b6c">'),
                substr_count($html, needle: ' style="object-fit:cover"'),
            ],
        );
    }

    #[Test]
    public function letsATemplatesPictureStyleReplaceThePlaceholderColour(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(colour: '#7a8b6c'),
            new PictureOptions('Harbour', picture: new ElementAttributes(attributes: ['style' => 'padding-top:50%'])),
        );

        static::assertSame(
            [false, true],
            [str_contains($html, 'background-color'), str_starts_with($html, '<picture style="padding-top:50%">')],
        );
    }

    #[Test]
    public function loadsAPriorityImageStraightAway(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(),
            new PictureOptions('Harbour', loading: Loading::Priority),
        );

        static::assertSame(
            '<picture>' . self::AVIF . '<img ' . self::IMG . ' fetchpriority="high" decoding="async"></picture>',
            $html,
        );
    }

    #[Test]
    public function loadsAPriorityOriginalStraightAway(): void
    {
        $html = PictureMarkup::original(
            new OriginalImage('/a.jpg', 800, 600),
            new PictureOptions('A', loading: Loading::Priority),
        );

        static::assertSame(
            '<img src="/a.jpg" width="800" height="600" alt="A" fetchpriority="high" decoding="async">',
            $html,
        );
    }

    #[Test]
    public function putsArtDirectedSourcesFirst(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(),
            new PictureOptions('Harbour', loading: Loading::Lazysrc),
            new ArtDirection('(max-width: 600px)', PictureSourcesFactory::make('square', sizes: '90vw')),
        );

        static::assertStringStartsWith(
            '<picture><source media="(max-width: 600px)" type="image/avif" data-lazysrc-srcset="/square-480.avif 480w, /square-960.avif 960w" data-lazysrc-sizes="90vw" width="960" height="720">'
                . '<source media="(max-width: 600px)" type="image/jpeg" data-lazysrc-srcset="/square-480.jpg 480w, /square-960.jpg 960w" data-lazysrc-sizes="90vw" width="960" height="720">'
                . '<source type="image/avif" data-lazysrc-srcset',
            $html,
        );
    }

    #[Test]
    public function putsClassesAndAttributesOnThePictureToo(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(),
            new PictureOptions('Harbour', picture: new ElementAttributes('frame', ['data-reveal' => true])),
        );

        static::assertStringStartsWith('<picture class="frame" data-reveal><source ', $html);
    }

    #[Test]
    public function showsNothingBelowABlankSourcesMediaQuery(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(),
            new PictureOptions('Harbour', loading: Loading::Lazysrc),
            new ArtDirection('(max-width: 599px)'),
        );

        static::assertStringStartsWith(
            '<picture><source media="(max-width: 599px)" data-lazysrc-srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="><source type="image/avif"',
            $html,
        );
    }

    #[Test]
    public function usesTheSizesOptionOverTheSizeSets(): void
    {
        $html = PictureMarkup::picture(PictureSourcesFactory::make(), new PictureOptions('Harbour', sizes: '50vw'));

        static::assertSame(2, substr_count($html, needle: 'sizes="50vw"'));
    }

    #[Test]
    public function writesANativeLazyPictureWithExtraAttributes(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(),
            new PictureOptions('Harbour', image: new ElementAttributes('hero', [
                'id'     => 'top',
                'hidden' => true,
                'title'  => false,
                'data-n' => 2.5,
            ])),
        );

        static::assertSame(
            '<picture>'
                . self::AVIF
                . '<img '
                . self::IMG
                . ' class="hero" loading="lazy" decoding="async" id="top" hidden data-n="2.5"></picture>',
            $html,
        );
    }

    #[Test]
    public function writesBlankSourcesWithRealAttributesWhenNotLazy(): void
    {
        $html = PictureMarkup::picture(
            PictureSourcesFactory::make(),
            new PictureOptions('Harbour'),
            new ArtDirection('(max-width: 599px)'),
        );

        static::assertStringStartsWith(
            '<picture><source media="(max-width: 599px)" srcset="data:image/gif;base64,',
            $html,
        );
    }
}
