<?php

declare(strict_types=1);

namespace parallelogram\images\test\TestAsset\Fake;

use Override;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\VariantGeneratorInterface;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

/**
 * Returns a fixed body, or fails when told to, and records each request.
 *
 * @internal
 */
final class RecordingGenerator implements VariantGeneratorInterface
{
    /**
     * @var list<string> Paths it was asked to generate.
     */
    public array $generated = [];

    public function __construct(
        private readonly bool $fails = false,
    ) {}

    #[Override]
    public function generate(SourceImage $source, VariantFile $file): string
    {
        $this->generated[] = $file->path;

        if ($this->fails) {
            throw new GenerationFailedException("Could not generate {$file->path}.");
        }

        return "bytes of {$file->path}";
    }
}
