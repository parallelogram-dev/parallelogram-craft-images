<?php

declare(strict_types=1);

namespace parallelogram\images\backends;

use Closure;
use Override;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\VariantGeneratorInterface;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

/**
 * Hands each variant to the generator its volume uses.
 *
 * Volumes on a remote service never reach here in normal use, because pages
 * link to the service directly; if one does, it is refused like any other
 * variant that can't be made.
 *
 * @api
 */
final readonly class VolumeRoutingGenerator implements VariantGeneratorInterface
{
    /**
     * @param array<string, VariantGeneratorInterface> $generators Local generators, keyed by backend value.
     * @param Closure(string): ?string $volumeOf The UID of an asset's volume, by asset UID.
     */
    public function __construct(
        private BackendChoice $choice,
        private BackendAvailability $availability,
        private array $generators,
        private Closure $volumeOf,
    ) {}

    #[Override]
    public function generate(SourceImage $source, VariantFile $file): string
    {
        $chosen = $this->choice->forVolume(($this->volumeOf)($source->uid));
        $kind   = $this->availability->resolve($chosen);

        if (null === $kind) {
            throw new GenerationFailedException("The {$chosen->value} backend isn't available on this server.");
        }

        $generator = $this->generators[$kind->value]
            ?? throw new GenerationFailedException(
                "{$kind->value} makes images itself; pages should link to it directly.",
            );

        return $generator->generate($source, $file);
    }
}
