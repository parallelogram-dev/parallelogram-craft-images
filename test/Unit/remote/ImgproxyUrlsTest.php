<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\remote;

use parallelogram\images\remote\ImgproxySigner;
use parallelogram\images\remote\ImgproxyUrls;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImgproxyUrls::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(ImgproxySigner::class)]
#[UsesClass(Processing::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[Group('unit')]
final class ImgproxyUrlsTest extends TestCase
{
    private const string ORIGINAL = 'https://www.example.test/uploads/harbour.jpg';

    /**
     * Base64url of {@see ORIGINAL}, without padding.
     */
    private const string ENCODED = 'aHR0cHM6Ly93d3cuZXhhbXBsZS50ZXN0L3VwbG9hZHMvaGFyYm91ci5qcGc';

    /**
     * @return array<string, array{Box, Format, string}>
     */
    public static function modeProvider(): array
    {
        return [
            'proportional'  => [new Box(480, null, Mode::Crop), Format::Avif, 'rs:fit:480:0:0'],
            'crop'          => [new Box(480, 360, Mode::Crop), Format::Avif, 'rs:fill:480:360:0/g:fp:0.2500:0.7500'],
            'fit'           => [new Box(480, 360, Mode::Fit), Format::Avif, 'rs:fit:480:360:0'],
            'stretch'       => [new Box(480, 360, Mode::Stretch), Format::Avif, 'rs:force:480:360:0'],
            'letterbox'     => [new Box(480, 360, Mode::Letterbox), Format::Webp, 'rs:fit:480:360:0:1'],
            'letterbox jpg' => [new Box(480, 360, Mode::Letterbox), Format::Jpg, 'rs:fit:480:360:0:1/bg:255:255:255'],
        ];
    }

    private static function signer(): ImgproxySigner
    {
        return new ImgproxySigner('736563726574', saltHex: '68656C6C6F');
    }

    private static function source(): SourceImage
    {
        return SourceImageFactory::make(focalPoint: new FocalPoint(0.25, y: 0.75));
    }

    private static function urls(Processing $processing): ImgproxyUrls
    {
        return new ImgproxyUrls('https://img.example.test/', self::signer(), $processing);
    }

    #[DataProvider('modeProvider')]
    #[Test]
    public function buildsASignedUrlForEachMode(Box $box, Format $format, string $resizing): void
    {
        $path = "/{$resizing}/q:60/ar:1/scp:1/sm:1/sh:0.50/" . self::ENCODED . ".{$format->value}";

        static::assertSame(
            'https://img.example.test/' . self::signer()->sign($path) . $path,
            self::urls(new Processing())
                ->urlFor(self::source(), new Variant('card', $box, $format, quality: 60), self::ORIGINAL),
        );
    }

    #[Test]
    public function passesProcessingThatIsTurnedOff(): void
    {
        $url = self::urls(new Processing(
            autoOrient: false,
            convertToSrgb: false,
            sharpenAmount: 0.0,
        ))
            ->urlFor(
                self::source(),
                new Variant('card', new Box(480, null, Mode::Crop), Format::Jpg, quality: 82),
                self::ORIGINAL,
            );

        static::assertStringContainsString('/q:82/ar:0/scp:0/sm:1/' . self::ENCODED . '.jpg', $url);
    }
}
