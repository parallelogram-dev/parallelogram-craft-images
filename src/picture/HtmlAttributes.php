<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use function htmlspecialchars;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Writes escaped HTML attributes. True writes the name alone; false and null
 * leave the attribute out.
 *
 * @internal
 */
final class HtmlAttributes
{
    /**
     * @param array<string, scalar|null> $attributes Names already checked as attribute names.
     */
    public static function render(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= self::attribute($name, $value);
        }

        return $html;
    }

    private static function attribute(string $name, string|int|float|bool|null $value): string
    {
        if (null === $value || false === $value) {
            return '';
        }

        if (true === $value) {
            return " {$name}";
        }

        return (
            " {$name}=\""
                . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, encoding: 'UTF-8')
                . '"'
        );
    }
}
