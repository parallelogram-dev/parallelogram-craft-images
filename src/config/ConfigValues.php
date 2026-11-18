<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function ctype_digit;
use function intval;
use function is_array;
use function is_int;
use function is_string;
use function trim;

/**
 * Narrows untyped values read from project config, config files or a posted
 * form. The list and map readers return null when the value is the wrong
 * shape, so the caller can say what was expected.
 *
 * @internal
 */
final class ConfigValues
{
    /**
     * A map of string keys to integers.
     *
     * @return array<string, int>|null
     */
    public static function integerMap(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $map = [];

        foreach (array_filter($value, is_int(...)) as $key => $integer) {
            if (! is_string($key)) {
                return null;
            }

            $map[$key] = $integer;
        }

        return count($map) === count($value) ? $map : null;
    }

    /**
     * A list of whole numbers, given as integers or digit strings.
     *
     * @return list<int>|null
     */
    public static function integers(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $integers = array_filter($value, is_int(...));
        $digits   = array_filter(array_filter($value, is_string(...)), ctype_digit(...));

        if ((count($integers) + count($digits)) !== count($value)) {
            return null;
        }

        return [...array_values($integers), ...array_map(intval(...), array_values($digits))];
    }

    /**
     * Numbers posted from a form, keyed by name: blanks are dropped, digit
     * strings become integers, and anything else becomes 0 so validation
     * reports it as out of range.
     *
     * @param array<array-key, mixed> $posted
     *
     * @return array<string, int>
     */
    public static function postedIntegers(array $posted): array
    {
        $integers = [];

        foreach (array_keys($posted) as $key) {
            $integer = self::postedInteger($posted[$key] ?? null);

            if (null === $integer) {
                continue;
            }

            $integers[(string) $key] = $integer;
        }

        return $integers;
    }

    /**
     * A trimmed string, or an empty string for anything else.
     */
    public static function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * A list of strings.
     *
     * @return list<string>|null
     */
    public static function strings(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $strings = array_filter($value, is_string(...));

        return count($strings) === count($value) ? array_values($strings) : null;
    }

    /**
     * One posted number: null when blank, 0 when it isn't a whole number.
     */
    private static function postedInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        $text = self::string($value);

        if ('' === $text) {
            return null;
        }

        return ctype_digit($text) ? (int) $text : 0;
    }
}
