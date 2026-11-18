<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use function array_replace;

/**
 * The class and other attributes a template gives one element of a picture,
 * the `<img>` or the `<picture>` around it.
 *
 * @api
 */
final readonly class ElementAttributes
{
    /**
     * @param array<string, scalar|null> $attributes True writes the name alone; false and null leave it out.
     */
    public function __construct(
        public ?string $class = null,
        public array $attributes = [],
    ) {}

    /**
     * Every attribute to write, the class first. Attributes given in
     * `attributes` replace ones of the same name the plugin writes.
     *
     * @return array<string, scalar|null>
     */
    public function all(): array
    {
        return array_replace(['class' => $this->class], $this->attributes);
    }
}
