<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

/**
 * Makes a variant file unless it exists: generated once (other requests for
 * the same file wait on a lock) and saved. Shared by the request handler and
 * warming, so both behave the same when two things want one file.
 *
 * @api
 */
final readonly class VariantMaker
{
    public function __construct(
        private VariantGeneratorInterface $generator,
        private VariantStoreInterface $store,
        private VariantLockInterface $lock,
    ) {}

    /**
     * @throws StorageFailedException
     */
    public function ensure(SourceImage $source, VariantFile $file): MakeResult
    {
        if ($this->store->exists($file->path)) {
            return MakeResult::Present;
        }

        if (! $this->lock->acquire($file->path)) {
            return $this->store->exists($file->path) ? MakeResult::Present : MakeResult::Failed;
        }

        try {
            return $this->generate($source, $file);
        } catch (GenerationFailedException) {
            return MakeResult::Failed;
        } finally {
            $this->lock->release($file->path);
        }
    }

    /**
     * @throws GenerationFailedException
     * @throws StorageFailedException
     */
    private function generate(SourceImage $source, VariantFile $file): MakeResult
    {
        if ($this->store->exists($file->path)) {
            return MakeResult::Present;
        }

        $this->store->write($file->path, $this->generator->generate($source, $file));

        return MakeResult::Made;
    }
}
