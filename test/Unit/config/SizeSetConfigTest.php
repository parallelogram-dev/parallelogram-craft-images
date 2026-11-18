<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\config;

use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SizeSetConfig::class)]
#[CoversClass(EncodingConfig::class)]
#[CoversClass(FitConfig::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[Group('unit')]
final class SizeSetConfigTest extends TestCase
{
    private const array DEFAULTS = [Format::Avif, Format::Jpg];

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigProvider(): array
    {
        $valid = ['widths' => [480], 'sizes' => '100vw'];

        return [
            'widths not a list'   => [['widths' => 480] + $valid, '“card” needs a list of widths in whole pixels.'],
            'widths with units'   => [['widths' => '480px'] + $valid, '“card” has “480px” as a width.'],
            'unknown mode'        => [['mode' => 'zoom'] + $valid, '“card” has an unknown mode “zoom”.'],
            'unknown format'      => [['formats' => ['gif']] + $valid, '“card” lists “gif”'],
            'formats not names'   => [['formats' => [1]] + $valid, '“card” has formats that aren\'t a list of names.'],
            'quality not numbers' => [
                ['quality' => ['avif' => 'high']] + $valid,
                'quality values that aren\'t formats',
            ],
            'bad ratio'           => [['ratio' => 'wide'] + $valid, '“wide” is not an aspect ratio.'],
        ];
    }

    #[Test]
    public function readsAFullConfig(): void
    {
        $sizeSet = SizeSetConfig::fromArray(
            'card',
            [
                'widths'  => [960, 480],
                'sizes'   => '50vw',
                'ratio'   => '4:3',
                'mode'    => 'fit',
                'formats' => ['webp', 'jpg'],
                'quality' => ['webp' => 70],
            ],
            self::DEFAULTS,
        );

        static::assertSame(
            [
                'widths'  => [480, 960],
                'sizes'   => '50vw',
                'ratio'   => '4:3',
                'mode'    => 'fit',
                'formats' => ['webp', 'jpg'],
                'quality' => ['webp' => 70],
            ],
            SizeSetConfig::toArray($sizeSet),
        );
    }

    #[Test]
    public function readsWidthsTypedAsText(): void
    {
        $sizeSet = SizeSetConfig::fromArray('card', ['widths' => '480, 720 960', 'sizes' => '100vw'], self::DEFAULTS);

        static::assertSame('480, 720, 960', SizeSetConfig::widthsToString($sizeSet));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigProvider')]
    #[Test]
    public function rejectsInvalidConfig(array $config, string $message): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage($message);

        SizeSetConfig::fromArray('card', $config, self::DEFAULTS);
    }

    #[Test]
    public function usesDefaultsForOptionalValues(): void
    {
        $sizeSet = SizeSetConfig::fromArray('card', ['widths' => [480], 'sizes' => '100vw'], self::DEFAULTS);

        static::assertSame(
            [[Format::Avif, Format::Jpg], Mode::Crop, null],
            [$sizeSet->encoding->formats, $sizeSet->fit->mode, $sizeSet->fit->ratio],
        );
    }
}
