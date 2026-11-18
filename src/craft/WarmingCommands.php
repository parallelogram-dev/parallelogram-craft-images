<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;
use parallelogram\images\delivery\StorageFailedException;
use yii\console\ExitCode;

/**
 * What the `p-images/warm`, `audit`, `rebuild` and `gc` commands do. The
 * console controllers only print the outcome.
 *
 * @internal
 */
final readonly class WarmingCommands
{
    private const string UNCONFIGURED = 'Image variants are off: choose a variant filesystem and prefix in the plugin settings.';

    public function __construct(
        private ?Warming $warming,
    ) {}

    /**
     * Counts the files each image is missing. Fails when any are, or any
     * image couldn't be checked, so it can guard a deploy.
     *
     * @param (Closure(int, int): void)|null $progress
     */
    public function audit(?string $volume, ?string $sizeSet, ?Closure $progress = null): CommandOutcome
    {
        return $this->run(Operation::Audit, $volume, $sizeSet, $progress);
    }

    /**
     * Removes variants nothing can ask for any more.
     */
    public function gc(): CommandOutcome
    {
        if (null === $this->warming) {
            return new CommandOutcome(ExitCode::UNSPECIFIED_ERROR, [self::UNCONFIGURED]);
        }

        try {
            $result = $this->warming->sweep();
        } catch (StorageFailedException $exception) {
            return new CommandOutcome(ExitCode::UNSPECIFIED_ERROR, [$exception->getMessage()]);
        }

        return new CommandOutcome(ExitCode::OK, [
            "Removed {$result->assetFolders} folders of deleted images and {$result->staleFolders} of out-of-date "
                . "variants, checking {$result->checkedAssets} images.",
        ]);
    }

    /**
     * Deletes every variant of each image and makes the assigned ones again.
     *
     * @param (Closure(int, int): void)|null $progress
     */
    public function rebuild(?string $volume, ?Closure $progress = null): CommandOutcome
    {
        return $this->run(Operation::Rebuild, $volume, sizeSet: null, progress: $progress);
    }

    /**
     * Makes each image's missing variants.
     *
     * @param (Closure(int, int): void)|null $progress
     */
    public function warm(?string $volume, ?string $sizeSet, ?Closure $progress = null): CommandOutcome
    {
        return $this->run(Operation::Warm, $volume, $sizeSet, $progress);
    }

    /**
     * @param list<int> $ids
     * @param (Closure(int, int): void)|null $progress
     */
    private function perform(
        BatchWarming $batch,
        Operation $operation,
        array $ids,
        ?string $sizeSet,
        ?Closure $progress,
    ): BatchResult {
        if (Operation::Audit === $operation) {
            return $batch->audit($ids, $sizeSet, $progress);
        }

        if (Operation::Rebuild === $operation) {
            return $batch->rebuild($ids, $progress);
        }

        return $batch->warm($ids, $sizeSet, $progress);
    }

    /**
     * @param (Closure(int, int): void)|null $progress
     */
    private function run(Operation $operation, ?string $volume, ?string $sizeSet, ?Closure $progress): CommandOutcome
    {
        if (null === $this->warming) {
            return new CommandOutcome(ExitCode::UNSPECIFIED_ERROR, [self::UNCONFIGURED]);
        }

        $batch = new BatchWarming($this->warming);
        $ids   = $batch->imageIds($volume);

        if (null === $ids) {
            return new CommandOutcome(ExitCode::UNSPECIFIED_ERROR, ["There's no volume with the handle “{$volume}”."]);
        }

        $result = $this->perform($batch, $operation, $ids, $sizeSet, $progress);
        $failed = 0 !== $result->missing || 0 !== $result->files->failed;

        return new CommandOutcome($failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK, [
            $result->summary($operation->value),
        ]);
    }
}
