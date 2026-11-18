<?php

declare(strict_types=1);

namespace parallelogram\images\colour;

use parallelogram\images\delivery\GenerationFailedException;

/**
 * Reads a small sample of an image's pixels for {@see DominantColour}.
 *
 * @api
 */
interface ColourSamplerInterface
{
    public const int SIZE = 32;

    /**
     * The image shrunk to at most 32×32, as its opaque pixels' red, green and
     * blue values. Pixels more than half transparent are left out.
     *
     * @return list<array{int, int, int}>
     *
     * @throws GenerationFailedException When the image can't be read.
     */
    public function pixels(string $bytes): array;
}
