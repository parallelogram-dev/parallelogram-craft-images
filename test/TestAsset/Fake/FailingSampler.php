<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Override;
use parallelogram\images\colour\ColourSamplerInterface;
use parallelogram\images\delivery\GenerationFailedException;

/**
 * A sampler that can't read any image.
 *
 * @internal
 */
final class FailingSampler implements ColourSamplerInterface
{
    #[Override]
    public function pixels(string $bytes): array
    {
        throw new GenerationFailedException('The image is damaged.');
    }
}
