<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\warming\WarmResult;

use function implode;

/**
 * What a run over many images did.
 *
 * @internal
 */
final readonly class BatchResult
{
    public WarmResult $files;

    /**
     * @param int $images Images looked at.
     * @param int $skipped Images that aren't resized here, or are gone.
     * @param int $missing Files found missing when checking.
     */
    public function __construct(
        public int $images = 0,
        public int $skipped = 0,
        ?WarmResult $files = null,
        public int $missing = 0,
    ) {
        $this->files = $files ?? new WarmResult();
    }

    /**
     * A run's counts added to this one's.
     */
    public function plus(self $other): self
    {
        return new self(
            $this->images + $other->images,
            $this->skipped + $other->skipped,
            $this->files->plus($other->files),
            $this->missing + $other->missing,
        );
    }

    /**
     * One line saying what a run did, e.g. "Warmed 12 images: 30 made, …".
     */
    public function summary(string $verb): string
    {
        $parts = [
            "{$this->files->made} made",
            "{$this->files->present} already there",
            "{$this->files->failed} failed",
            "{$this->missing} missing",
            "{$this->skipped} skipped",
        ];

        return "{$verb} {$this->images} images: " . implode(', ', $parts) . '.';
    }
}
