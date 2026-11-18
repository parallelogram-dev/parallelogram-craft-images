<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;
use craft\elements\Asset;
use parallelogram\images\picture\InvalidPictureException;
use parallelogram\images\picture\Loading;
use parallelogram\images\picture\PictureMarkup;
use parallelogram\images\picture\PictureOptions;
use parallelogram\images\picture\PictureSources;
use parallelogram\images\sizing\UnknownSizeSetException;
use parallelogram\images\variants\InvalidVariantException;

/**
 * What `asset.picture()` and `asset.variants()` do.
 *
 * Until variants are configured, and for files that aren't resized (SVGs,
 * GIFs, anything that isn't an image), pictures show the original file, with
 * an HTML comment saying why in dev mode. A template mistake, such as an
 * unknown size set or option, is an error in dev mode; in production it's
 * logged and the original is shown, so a page never breaks over one image.
 *
 * @internal
 */
final readonly class AssetPictures
{
    private const string NOT_CONFIGURED =
        'Parallelogram Images: variants are off until a variant filesystem and '
            . 'prefix are chosen, so this is the original file.';

    private const string NOT_RESIZED =
        'Parallelogram Images: this file is shown as uploaded, because it isn’t an '
            . 'image that can be resized (SVGs and GIFs never are).';

    private ?PictureSourcesFinder $finder;

    /**
     * @param Closure(string): void $warn Logs a problem that production pages hide.
     */
    public function __construct(
        ?ConfiguredVariants $variants,
        private bool $devMode,
        private Closure $warn,
        private Loading $defaultLoading = Loading::Lazy,
    ) {
        $this->finder = null === $variants ? null : new PictureSourcesFinder($variants);
    }

    /**
     * @param array<array-key, mixed> $options See {@see PictureOptions} and {@see ArtDirectionOption}.
     *
     * @throws InvalidPictureException|InvalidVariantException|UnknownSizeSetException In dev mode only.
     */
    public function picture(Asset $asset, string $sizeSet, array $options = []): string
    {
        try {
            return $this->render($asset, $sizeSet, $options);
        } catch (InvalidPictureException|InvalidVariantException|UnknownSizeSetException $exception) {
            $this->failed($exception);

            return PictureMarkup::original(AssetFiles::original($asset), new PictureOptions($asset->alt ?? ''));
        }
    }

    /**
     * The sources of one image in one size set, or null when there are none
     * to use.
     *
     * @throws InvalidVariantException|UnknownSizeSetException In dev mode only.
     */
    public function variants(Asset $asset, string $sizeSet): ?PictureSources
    {
        try {
            return $this->finder?->find($asset, $sizeSet);
        } catch (InvalidVariantException|UnknownSizeSetException $exception) {
            $this->failed($exception);

            return null;
        }
    }

    /**
     * @throws InvalidPictureException|InvalidVariantException|UnknownSizeSetException
     */
    private function failed(InvalidPictureException|InvalidVariantException|UnknownSizeSetException $exception): void
    {
        if ($this->devMode) {
            throw $exception;
        }

        ($this->warn)($exception->getMessage());
    }

    private function note(string $note): ?string
    {
        return $this->devMode ? $note : null;
    }

    /**
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidPictureException|InvalidVariantException|UnknownSizeSetException
     */
    private function render(Asset $asset, string $sizeSet, array $options): string
    {
        $pictureOptions = PictureOptions::fromArray($options, $asset->alt ?? '', $this->defaultLoading);

        if (null === $this->finder) {
            return PictureMarkup::original(
                AssetFiles::original($asset),
                $pictureOptions,
                $this->note(self::NOT_CONFIGURED),
            );
        }

        $sources = $this->finder->find($asset, $sizeSet);

        if (null === $sources) {
            return PictureMarkup::original(
                AssetFiles::original($asset),
                $pictureOptions,
                $this->note(self::NOT_RESIZED),
            );
        }

        return PictureMarkup::picture(
            $sources,
            $pictureOptions,
            ...$this->finder->artDirections($asset, $sizeSet, $options),
        );
    }
}
