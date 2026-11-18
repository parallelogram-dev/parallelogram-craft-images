<?php

declare(strict_types=1);

namespace parallelogram\images\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use Override;
use parallelogram\images\craft\Warming;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\Plugin;
use RuntimeException;

/**
 * Makes an image's variants after it's uploaded, replaced, or placed in a
 * field that has size sets assigned. After a replacement it then removes the
 * old file's variants, once the new ones exist.
 *
 * Making a file that's already there does nothing, so running this twice is
 * harmless.
 *
 * @api
 */
final class GenerateVariantsJob extends BaseJob
{
    public int $assetId = 0;

    public bool $clean = false;

    /**
     * @var list<string>|null Size sets to make, or null for those assigned to fields that accept the image's volume.
     */
    public ?array $sizeSets = null;

    /**
     * @throws StorageFailedException When the variant filesystem can't be reached.
     * @throws RuntimeException When some variants couldn't be made.
     */
    #[Override]
    public function execute($queue): void
    {
        $this->generate(Plugin::getInstance()?->warming(), Asset::find()->id($this->assetId)->status(null)->one());
    }

    #[Override]
    protected function defaultDescription(): ?string
    {
        return Craft::t('p-images', message: 'Generating image variants');
    }

    /**
     * @throws StorageFailedException
     * @throws RuntimeException
     */
    private function generate(?Warming $warming, mixed $asset): void
    {
        if (null === $warming || ! $asset instanceof Asset) {
            return;
        }

        $sizeSets = $this->sizeSets ?? $warming->scope->forVolume($asset);
        $result   = $this->clean ? $warming->warmAndClean($asset, $sizeSets) : $warming->warm($asset, $sizeSets);

        if (null !== $result && $result->failed > 0) {
            throw new RuntimeException("{$result->failed} variant(s) of {$asset->filename} couldn't be made.");
        }
    }
}
