<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\config;

use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\config\SizeSetRows;
use parallelogram\images\config\SizeSetTable;
use parallelogram\images\sizing\AspectRatio;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SizeSetRows::class)]
#[CoversClass(SizeSetTable::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(Format::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetConfig::class)]
#[Group('unit')]
final class SizeSetRowsTest extends TestCase
{
    #[Test]
    public function buildsTableRowsFromSizeSets(): void
    {
        $rows = SizeSetTable::rows([SizeSetFactory::make(
            widths: [480, 960],
            ratio: '4:3',
        )]);

        static::assertSame(
            [[
                'handle' => 'card',
                'ratio'  => '4:3',
                'widths' => '480, 960',
                'sizes'  => '100vw',
                'mode'   => 'crop',
                'avif'   => true,
                'jpg'    => true,
                'png'    => false,
                'webp'   => false,
            ]],
            $rows,
        );
    }

    #[Test]
    public function findsRepeatedHandles(): void
    {
        $rows = [
            ['handle' => 'card'],
            ['handle' => 'tile'],
            ['handle' => 'card'],
            ['handle' => ''],
            ['handle' => ''],
            'junk',
        ];

        static::assertSame(['card'], SizeSetTable::duplicateHandles($rows));
    }

    #[Test]
    public function keepsQualityTheTableDoesNotShow(): void
    {
        $configs = SizeSetRows::toConfigs(
            [['handle' => 'card', 'widths' => '480', 'sizes' => '100vw']],
            ['card' => ['quality' => ['avif' => 45]]],
        );

        static::assertSame(['avif' => 45], $configs['card']['quality'] ?? null);
    }

    #[Test]
    public function readsPostedRowsIntoConfigs(): void
    {
        $rows = [
            [
                'handle' => ' card ',
                'widths' => '480, 960',
                'sizes'  => '50vw',
                'ratio'  => '4:3',
                'mode'   => 'crop',
                'avif'   => '1',
                'jpg'    => true,
                'png'    => '',
            ],
            ['handle' => '', 'widths' => '100'],
            'not a row',
        ];

        static::assertSame(
            [
                'card' => [
                    'widths'  => '480, 960',
                    'sizes'   => '50vw',
                    'ratio'   => '4:3',
                    'mode'    => 'crop',
                    'formats' => ['avif', 'jpg'],
                    'quality' => [],
                ],
            ],
            SizeSetRows::toConfigs($rows, ['card' => 'not an array']),
        );
    }
}
