<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Override;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\SourceFileInterface;
use parallelogram\images\variants\SourceImage;

/**
 * Returns the same original file for every image, or fails when it has none.
 *
 * @internal
 */
final readonly class StaticSourceFile implements SourceFileInterface
{
    public function __construct(
        private ?string $contents,
    ) {}

    #[Override]
    public function contents(SourceImage $source): string
    {
        return $this->contents ?? throw new GenerationFailedException("No file for {$source->uid}.");
    }
}
