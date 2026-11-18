<?php

declare(strict_types=1);

namespace parallelogram\images\colour;

use function is_string;
use function preg_match;
use function strtolower;

/**
 * A stored placeholder colour, checked: `#rrggbb` in lower case, or nothing.
 *
 * @api
 */
final class HexColour
{
    public static function normalise(mixed $value): ?string
    {
        return is_string($value) && 1 === preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : null;
    }
}
