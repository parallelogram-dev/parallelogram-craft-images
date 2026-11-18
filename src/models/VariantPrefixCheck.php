<?php

declare(strict_types=1);

namespace parallelogram\images\models;

use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\VariantPath;

use function str_starts_with;

/**
 * Checks the variant prefix setting. An empty prefix just means variants
 * aren't configured yet, and an environment variable or alias is checked when
 * it is used.
 *
 * @internal
 */
final class VariantPrefixCheck
{
    public static function error(string $prefix): ?string
    {
        if ('' === $prefix || str_starts_with($prefix, '$') || str_starts_with($prefix, '@')) {
            return null;
        }

        try {
            new VariantPath($prefix);
        } catch (InvalidVariantException $exception) {
            return $exception->getMessage();
        }

        return null;
    }
}
