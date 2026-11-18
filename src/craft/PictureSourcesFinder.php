<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use parallelogram\images\picture\ArtDirection;
use parallelogram\images\picture\InvalidPictureException;
use parallelogram\images\picture\PictureSources;
use parallelogram\images\picture\PictureSourcesBuilder;
use parallelogram\images\picture\RemoteVariantLinks;
use parallelogram\images\picture\VariantLinksInterface;
use parallelogram\images\sizing\AlternateImages;
use parallelogram\images\sizing\UnknownSizeSetException;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;

use function array_key_exists;

/**
 * Works out an asset's sources in a size set, linking to the variant
 * filesystem or, for volumes that use one, straight to the image service.
 *
 * @internal
 */
final readonly class PictureSourcesFinder
{
    public function __construct(
        private ConfiguredVariants $variants,
        private AlternateImages $alternates = new AlternateImages(),
    ) {}

    /**
     * The art-directed alternatives the `sources` option asks for, or the
     * size set's alternate image when the option is left out, skipping any
     * whose asset isn't resized.
     *
     * @param array<array-key, mixed> $options
     *
     * @return list<ArtDirection>
     *
     * @throws InvalidPictureException|InvalidVariantException|UnknownSizeSetException
     */
    public function artDirections(Asset $asset, string $sizeSet, array $options): array
    {
        $directions = [];

        $chosen = array_key_exists('sources', $options)
            ? ArtDirectionOption::listFrom($options)
            : $this->alternateFor($asset, $sizeSet);

        foreach ($chosen as $option) {
            $sources = $this->find($option->asset ?? $asset, $option->sizeSet ?? $sizeSet);

            if (null !== $sources) {
                $directions[] = new ArtDirection($option->media, $sources);
            }
        }

        return $directions;
    }

    /**
     * The asset's sources, or null when it isn't an image that's resized.
     *
     * @throws InvalidVariantException When a link can't be made.
     * @throws UnknownSizeSetException When there's no such size set.
     */
    public function find(Asset $asset, string $sizeSet): ?PictureSources
    {
        $set    = $this->variants->catalogue->get($sizeSet);
        $source = AssetSource::from($asset);

        if (null === $source) {
            return null;
        }

        return PictureSourcesBuilder::build(
            $source,
            $set,
            $this->variants->planner->filesFor($source, $set),
            $this->links($asset, $source),
            PlaceholderStore::colourOf($asset),
        );
    }

    /**
     * @return list<ArtDirectionOption>
     */
    private function alternateFor(Asset $asset, string $sizeSet): array
    {
        $alternate = $this->alternates->for($sizeSet);
        $related   = null === $alternate ? null : RelatedAsset::in($asset, $alternate->fieldUid);

        if (null === $alternate || null === $related) {
            return [];
        }

        return [new ArtDirectionOption($alternate->media, $related, $alternate->sizeSet)];
    }

    /**
     * @throws InvalidVariantException When the image service can't fetch this asset.
     */
    private function links(Asset $asset, SourceImage $source): VariantLinksInterface
    {
        $service = $this->variants->backends->serviceFor(AssetFiles::volumeUid($asset));

        if (null === $service) {
            return $this->variants->storage->links;
        }

        $url = AssetFiles::url($asset) ?? throw new InvalidVariantException(
            "Asset {$source->uid} has no public URL, so the image service can't fetch it.",
        );

        return new RemoteVariantLinks($service, $source, $url);
    }
}
