<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\config;

use parallelogram\images\config\AlternateConfig;
use parallelogram\images\config\AlternateRows;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\sizing\AlternateImage;
use parallelogram\images\sizing\AlternateImages;
use parallelogram\images\sizing\InvalidSizeSetException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AlternateConfig::class)]
#[CoversClass(AlternateRows::class)]
#[UsesClass(AlternateImage::class)]
#[UsesClass(AlternateImages::class)]
#[UsesClass(ConfigValues::class)]
#[Group('unit')]
final class AlternateConfigTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidConfigProvider(): array
    {
        return [
            'not a list'    => ['wide', 'must be a list keyed by size set'],
            'entry a value' => [['wide' => 'field-1'], 'Each alternate image needs a field and a media query.'],
            'no field'      => [['wide' => ['media' => 'print']], 'Choose the field that holds “wide”'],
            'no media'      => [['wide' => ['field' => 'field-1']], 'Give “wide”’s alternate image a media query'],
        ];
    }

    #[Test]
    public function readsAndWritesStoredAlternates(): void
    {
        $config = [
            'wide' => ['field' => 'field-1', 'media' => '(max-width: 430px)', 'sizeSet' => 'square'],
            'card' => ['field' => 'field-2', 'media' => 'print', 'sizeSet' => ''],
        ];

        static::assertSame(
            [
                'wide' => ['field' => 'field-1', 'media' => '(max-width: 430px)', 'sizeSet' => 'square'],
                'card' => ['field' => 'field-2', 'media' => 'print', 'sizeSet' => null],
            ],
            AlternateConfig::toArray(AlternateConfig::fromArray($config)),
        );
    }

    #[Test]
    public function readsTableRowsSkippingThoseWithoutASizeSet(): void
    {
        static::assertSame(
            [
                'wide' => ['field' => 'field-1', 'media' => 'print', 'sizeSet' => null],
                'card' => ['field' => 'field-2', 'media' => 'screen', 'sizeSet' => 'square'],
            ],
            AlternateRows::toConfigs([
                '__submitted' => '1',
                ['sizeSet' => '', 'field' => 'field-9', 'media' => 'screen'],
                ['sizeSet' => 'wide', 'field' => 'field-1', 'media' => 'print', 'alternateSizeSet' => ''],
                ['sizeSet' => 'card', 'field' => 'field-2', 'media' => 'screen', 'alternateSizeSet' => 'square'],
            ]),
        );
    }

    /**
     * @param mixed $config
     */
    #[DataProvider('invalidConfigProvider')]
    #[Test]
    public function refusesAlternatesItCantUse(mixed $config, string $message): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage($message);

        AlternateConfig::fromArray($config);
    }

    #[Test]
    public function writesTableRows(): void
    {
        static::assertSame(
            [
                ['sizeSet' => 'wide', 'field' => 'field-1', 'media' => 'print', 'alternateSizeSet' => 'square'],
                ['sizeSet' => 'card', 'field' => 'field-2', 'media' => 'screen', 'alternateSizeSet' => ''],
            ],
            AlternateRows::rows(new AlternateImages([
                'wide' => new AlternateImage('field-1', 'print', 'square'),
                'card' => new AlternateImage('field-2', 'screen'),
            ])),
        );
    }
}
