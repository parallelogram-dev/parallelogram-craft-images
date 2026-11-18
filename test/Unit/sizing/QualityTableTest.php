<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QualityTable::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[Group('unit')]
final class QualityTableTest extends TestCase
{
    /**
     * @return array<string, array{int}>
     */
    public static function outOfRangeProvider(): array
    {
        return [
            'zero'      => [0],
            'above 100' => [101],
        ];
    }

    #[Test]
    public function fallsBackToTheFormatDefault(): void
    {
        static::assertSame(82, (new QualityTable())->for(Format::Jpg));
    }

    #[Test]
    public function keepsOnlyExplicitValuesForSaving(): void
    {
        static::assertSame(['avif' => 50], (new QualityTable(['avif' => 50]))->toArray());
    }

    #[Test]
    public function letsOverridesWin(): void
    {
        $site      = new QualityTable(['avif' => 50, 'jpg' => 75]);
        $overrides = new QualityTable(['avif' => 40]);

        static::assertSame(['avif' => 40, 'jpg' => 75], $site->overriddenBy($overrides)->toArray());
    }

    #[DataProvider('outOfRangeProvider')]
    #[Test]
    public function rejectsQualityOutsideOneToOneHundred(int $quality): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('Avif quality must be a whole number from 1 to 100.');

        new QualityTable(['avif' => $quality]);
    }

    #[Test]
    public function rejectsUnknownFormats(): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('“gif” is not an image format.');

        new QualityTable(['gif' => 80]);
    }

    #[Test]
    public function returnsAnExplicitValue(): void
    {
        static::assertSame(55, (new QualityTable(['avif' => 55]))->for(Format::Avif));
    }
}
