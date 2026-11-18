<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\remote;

use parallelogram\images\remote\CloudflareUrls;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
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
#[CoversClass(CloudflareUrls::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(Processing::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[Group('unit')]
final class CloudflareUrlsTest extends TestCase
{
    private const string ORIGINAL = 'https://www.example.test/uploads/harbour.jpg';

    /**
     * @return array<string, array{Box, Format, string}>
     */
    public static function modeProvider(): array
    {
        return [
            'proportional' => [
                new Box(480, null, Mode::Crop),
                Format::Avif,
                'width=480,fit=scale-down,quality=60,format=avif',
            ],
            'crop'         => [
                new Box(480, 360, Mode::Crop),
                Format::Webp,
                'width=480,height=360,fit=crop,gravity=0.2500x0.7500,quality=60,format=webp',
            ],
            'fit'          => [
                new Box(480, 360, Mode::Fit),
                Format::Jpg,
                'width=480,height=360,fit=scale-down,quality=60,format=jpeg',
            ],
            'letterbox'    => [
                new Box(480, 360, Mode::Letterbox),
                Format::Avif,
                'width=480,height=360,fit=pad,quality=60,format=avif',
            ],
            'stretch'      => [
                new Box(480, 360, Mode::Stretch),
                Format::Avif,
                'width=480,height=360,fit=squeeze,quality=60,format=avif',
            ],
        ];
    }

    private static function source(): SourceImage
    {
        return SourceImageFactory::make(focalPoint: new FocalPoint(0.25, y: 0.75));
    }

    #[DataProvider('modeProvider')]
    #[Test]
    public function buildsATransformUrlForEachMode(Box $box, Format $format, string $options): void
    {
        $url = (new CloudflareUrls('https://www.example.test/', new Processing()))
            ->urlFor(self::source(), new Variant('card', $box, $format, quality: 60), self::ORIGINAL);

        static::assertSame(
            "https://www.example.test/cdn-cgi/image/{$options},metadata=none,anim=false,sharpen=0.50/" . self::ORIGINAL,
            $url,
        );
    }

    #[Test]
    public function leavesSharpeningOffWhenTheSiteDoes(): void
    {
        $url = (new CloudflareUrls('https://www.example.test', new Processing(sharpenAmount: 0.0)))
            ->urlFor(
                self::source(),
                new Variant('card', new Box(480, null, Mode::Crop), Format::Avif, quality: 60),
                self::ORIGINAL,
            );

        static::assertStringEndsWith(',metadata=none,anim=false/' . self::ORIGINAL, $url);
    }

    #[Test]
    public function refusesPng(): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage('Cloudflare Images can\'t make PNG files.');

        (new CloudflareUrls('https://www.example.test', new Processing()))
            ->urlFor(
                self::source(),
                new Variant('card', new Box(480, null, Mode::Crop), Format::Png, quality: 80),
                self::ORIGINAL,
            );
    }
}
