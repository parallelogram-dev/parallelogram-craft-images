<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function count;
use function is_array;
use function is_bool;
use function is_null;
use function is_scalar;
use function is_string;
use function preg_match;

/**
 * Reads typed values from the options hash a template passes, refusing
 * anything of the wrong type rather than guessing.
 *
 * @internal
 */
final class OptionReader
{
    private const string ATTRIBUTE_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:.-]*$/';

    /**
     * The `attributes` option: names and text, number, true, false or null
     * values.
     *
     * @param array<array-key, mixed> $options
     *
     * @return array<string, scalar|null>
     *
     * @throws InvalidPictureException
     */
    public static function attributes(array $options): array
    {
        if (! array_key_exists('attributes', $options)) {
            return [];
        }

        if (! is_array($options['attributes'])) {
            throw new InvalidPictureException('The attributes option must be a hash of attribute names and values.');
        }

        $given = $options['attributes'];

        $scalars = array_filter($given, is_scalar(...));
        $nulls   = array_filter($given, is_null(...));

        if ((count($scalars) + count($nulls)) !== count($given)) {
            throw new InvalidPictureException('Attribute values must be text, numbers, true, false or null.');
        }

        $attributes = [];

        foreach (array_keys($given) as $name) {
            if (! is_string($name) || 1 !== preg_match(self::ATTRIBUTE_NAME, $name)) {
                throw new InvalidPictureException("“{$name}” isn't an attribute name.");
            }

            $attributes[$name] = $scalars[$name] ?? null;
        }

        return $attributes;
    }

    /**
     * A true or false option, null when it's left out.
     *
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidPictureException
     */
    public static function flag(array $options, string $key): ?bool
    {
        if (! array_key_exists($key, $options)) {
            return null;
        }

        if (! is_bool($options[$key])) {
            throw new InvalidPictureException("The {$key} option must be true or false.");
        }

        return $options[$key];
    }

    /**
     * A text option, null when it's left out or null.
     *
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidPictureException
     */
    public static function text(array $options, string $key): ?string
    {
        if (null === ($options[$key] ?? null)) {
            return null;
        }

        if (! is_string($options[$key])) {
            throw new InvalidPictureException("The {$key} option must be text.");
        }

        return $options[$key];
    }
}
