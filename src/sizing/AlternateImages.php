<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

/**
 * The alternate image each size set uses, if any.
 *
 * @api
 */
final readonly class AlternateImages
{
    /**
     * @param array<string, AlternateImage> $bySizeSet Keyed by size set handle.
     */
    public function __construct(
        public array $bySizeSet = [],
    ) {}

    public function for(string $sizeSet): ?AlternateImage
    {
        return $this->bySizeSet[$sizeSet] ?? null;
    }

    /**
     * Alternates naming size sets the catalogue doesn't have.
     *
     * @return list<string>
     */
    public function problemsWith(SizeSetCatalogue $catalogue): array
    {
        $problems = [];

        foreach ($this->bySizeSet as $handle => $alternate) {
            if (! $catalogue->has($handle)) {
                $problems[] = "There's an alternate image for “{$handle}”, which isn't a size set.";
            }

            if (null !== $alternate->sizeSet && ! $catalogue->has($alternate->sizeSet)) {
                $problems[] = "“{$handle}” shows its alternate image as “{$alternate->sizeSet}”, which isn't a size set.";
            }
        }

        return $problems;
    }
}
