<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\config;

use parallelogram\images\config\ConfigValues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConfigValues::class)]
#[Group('unit')]
final class ConfigValuesTest extends TestCase
{
    /**
     * @return array<string, array{mixed, array<string, int>|null}>
     */
    public static function integerMapProvider(): array
    {
        return [
            'map'           => [['avif' => 50], ['avif' => 50]],
            'empty'         => [[], []],
            'integer keys'  => [[50], null],
            'string values' => [['avif' => '50'], null],
            'not an array'  => [50, null],
        ];
    }

    /**
     * @return array<string, array{mixed, list<int>|null}>
     */
    public static function integersProvider(): array
    {
        return [
            'integers'      => [[480, 960], [480, 960]],
            'digit strings' => [['480', '960'], [480, 960]],
            'mixed numbers' => [[480, '960'], [480, 960]],
            'not digits'    => [['480px'], null],
            'floats'        => [[4.5], null],
            'not a list'    => ['480', null],
        ];
    }

    /**
     * @return array<string, array{array<array-key, mixed>, array<string, int>}>
     */
    public static function postedIntegersProvider(): array
    {
        return [
            'digit strings'  => [['avif' => '55', 'jpg' => '80'], ['avif' => 55, 'jpg' => 80]],
            'blanks dropped' => [['avif' => '', 'jpg' => ' '], []],
            'integers kept'  => [['avif' => 0], ['avif' => 0]],
            'junk becomes 0' => [['avif' => 'high', 'jpg' => null], ['avif' => 0]],
        ];
    }

    /**
     * @return array<string, array{mixed, list<string>|null}>
     */
    public static function stringsProvider(): array
    {
        return [
            'strings'       => [['avif', 'jpg'], ['avif', 'jpg']],
            'keyed strings' => [['a' => 'avif'], ['avif']],
            'mixed'         => [['avif', 1], null],
            'not an array'  => ['avif', null],
        ];
    }

    /**
     * @param list<string>|null $expected
     */
    #[DataProvider('stringsProvider')]
    #[Test]
    public function readsAListOfStrings(mixed $value, ?array $expected): void
    {
        static::assertSame($expected, ConfigValues::strings($value));
    }

    /**
     * @param list<int>|null $expected
     */
    #[DataProvider('integersProvider')]
    #[Test]
    public function readsAListOfWholeNumbers(mixed $value, ?array $expected): void
    {
        static::assertSame($expected, ConfigValues::integers($value));
    }

    /**
     * @param array<string, int>|null $expected
     */
    #[DataProvider('integerMapProvider')]
    #[Test]
    public function readsAMapOfIntegers(mixed $value, ?array $expected): void
    {
        static::assertSame($expected, ConfigValues::integerMap($value));
    }

    /**
     * @param array<array-key, mixed> $posted
     * @param array<string, int> $expected
     */
    #[DataProvider('postedIntegersProvider')]
    #[Test]
    public function readsPostedNumbers(array $posted, array $expected): void
    {
        static::assertSame($expected, ConfigValues::postedIntegers($posted));
    }

    #[Test]
    public function trimsStringsAndBlanksEverythingElse(): void
    {
        static::assertSame(['card', ''], [ConfigValues::string(' card '), ConfigValues::string(42)]);
    }
}
