<?php

declare(strict_types=1);

namespace parallelogram\images\config;

use function array_keys;
use function count;

/**
 * Converts one of the prototype's "matrices" into a size set config. The
 * prototype listed each variant's width and height; a size set has a ladder of
 * widths and one optional aspect ratio for them all.
 *
 * @internal
 */
final class PrototypeMatrix
{
    /**
     * @var list<string>
     */
    public array $notes = [];

    /**
     * @param array<array-key, mixed> $matrix
     *
     * @return array<string, mixed>|null Null when nothing usable is left.
     */
    public function convert(string $handle, array $matrix): ?array
    {
        $widths = [];
        $ratios = [];

        foreach (PrototypeVariant::allIn($matrix) as $variant) {
            if ($variant->width < 1) {
                $this->notes[] = "“{$handle}” had a height-only variant, which size sets can't express. It was left out.";
                continue;
            }

            $widths[]                  = $variant->width;
            $ratios[$variant->ratio()] = true;
        }

        if ([] === $widths) {
            $this->notes[] = "“{$handle}” had no widths, so it wasn't converted.";

            return null;
        }

        return [
            'widths'  => $widths,
            'sizes'   => ConfigValues::string($matrix['sizes'] ?? null),
            'ratio'   => $this->sharedRatio($handle, array_keys($ratios)),
            'mode'    => ConfigValues::string($matrix['mode'] ?? null),
            'formats' => ConfigValues::strings($matrix['formats'] ?? []) ?? [],
            'quality' => [],
        ];
    }

    /**
     * The one ratio every variant shared, or null when they kept the source's
     * proportions or disagreed.
     *
     * @param list<string|int> $ratios
     */
    private function sharedRatio(string $handle, array $ratios): ?string
    {
        if (count($ratios) > 1) {
            $this->notes[] = "“{$handle}” mixed variants of different shapes. It now keeps each image's own proportions.";

            return null;
        }

        $ratio = (string) ($ratios[0] ?? '');

        return '' === $ratio ? null : $ratio;
    }
}
