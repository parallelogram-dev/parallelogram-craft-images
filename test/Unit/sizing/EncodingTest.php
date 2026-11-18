<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Format;
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
#[CoversClass(Encoding::class)]
#[UsesClass(Format::class)]
#[UsesClass(QualityTable::class)]
#[Group('unit')]
final class EncodingTest extends TestCase
{
    /**
     * @return array<string, array{non-empty-list<Format>, Format}>
     */
    public static function fallbackProvider(): array
    {
        return [
            'universal first'      => [[Format::Jpg, Format::Avif], Format::Jpg],
            'universal later'      => [[Format::Avif, Format::Webp, Format::Png, Format::Jpg], Format::Png],
            'no universal format'  => [[Format::Avif, Format::Webp], Format::Webp],
            'single modern format' => [[Format::Avif], Format::Avif],
        ];
    }

    /**
     * @param non-empty-list<Format> $formats
     */
    #[DataProvider('fallbackProvider')]
    #[Test]
    public function picksTheFirstUniversalFormatOrTheLast(array $formats, Format $expected): void
    {
        static::assertSame($expected, (new Encoding(new QualityTable(), ...$formats))->fallback);
    }

    #[Test]
    public function removesRepeatedFormatsKeepingOrder(): void
    {
        $encoding = new Encoding(new QualityTable(), Format::Avif, Format::Jpg, Format::Avif);

        static::assertSame([Format::Avif, Format::Jpg], $encoding->formats);
    }
}
