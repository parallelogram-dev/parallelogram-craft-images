<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\delivery\GenerationFailedException;
use yii\base\Exception;

use function array_chunk;
use function count;

/**
 * Works out placeholder colours for images already uploaded: those still
 * missing one, or every one when forced.
 *
 * @internal
 */
final class PlaceholderBackfill
{
    private int $made = 0;

    private int $skipped = 0;

    /**
     * @var list<string>
     */
    private array $failures = [];

    public function __construct(
        private readonly PlaceholderMaker $maker,
        private readonly bool $force = false,
    ) {}

    /**
     * @return list<string> One line per image that couldn't be read.
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * Fills in every image, returning whether all of them could be read.
     */
    public function run(): bool
    {
        foreach (array_chunk(Asset::find()->kind(Asset::KIND_IMAGE)->status(null)->ids(), length: 100) as $ids) {
            /** @var list<Asset> $assets An asset query returns assets unless asked for arrays. */
            $assets = Asset::find()->id($ids)->status(null)->all();

            foreach ($assets as $asset) {
                $this->fill($asset);
            }
        }

        return [] === $this->failures;
    }

    public function summary(): string
    {
        $failed = count($this->failures);

        return "Placeholder colours: {$this->made} worked out, {$this->skipped} skipped, {$failed} failed.";
    }

    private function fill(Asset $asset): void
    {
        if (! $this->force && null !== PlaceholderStore::colourOf($asset)) {
            ++$this->skipped;

            return;
        }

        try {
            $colour = $this->maker->make($asset);
        } catch (GenerationFailedException|Exception $exception) {
            $this->failures[] = "{$asset->filename}: {$exception->getMessage()}";

            return;
        }

        null === $colour ? ++$this->skipped : ++$this->made;
    }
}
