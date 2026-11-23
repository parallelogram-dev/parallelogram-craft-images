<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\Format;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Format::class)]
#[Group('unit')]
final class FormatTest extends TestCase
{
    /**
     * @return array<string, array{Format, int, bool, string}>
     */
    public static function formatProvider(): array
    {
        return [
            'avif' => [Format::Avif, 50, false, 'image/avif'],
            'jpg'  => [Format::Jpg, 82, true, 'image/jpeg'],
            'png'  => [Format::Png, 80, true, 'image/png'],
            'webp' => [Format::Webp, 80, false, 'image/webp'],
        ];
    }

    #[DataProvider('formatProvider')]
    #[Test]
    public function describesEachFormat(Format $format, int $quality, bool $universal, string $mimeType): void
    {
        static::assertSame(
            [$quality, $universal, $mimeType],
            [$format->defaultQuality(), $format->isUniversal(), $format->mimeType()],
        );
    }
}
