<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;
use parallelogram\images\models\Settings;
use parallelogram\images\picture\Loading;
use parallelogram\images\sizing\AlternateImages;
use parallelogram\images\sizing\InvalidSizeSetException;

/**
 * Builds what `asset.picture()` and `asset.variants()` use from the plugin
 * settings.
 *
 * @internal
 */
final readonly class PictureServiceFactory
{
    /**
     * @param Closure(string): void $warn Logs a problem that production pages hide.
     */
    public function __construct(
        private CraftEnvironmentInterface $environment,
        private bool $devMode,
        private Closure $warn,
    ) {}

    /**
     * The size sets' alternate images, or none when they can't be read.
     */
    private static function alternates(Settings $settings): AlternateImages
    {
        try {
            return $settings->alternateImages();
        } catch (InvalidSizeSetException) {
            return new AlternateImages();
        }
    }

    public function pictures(Settings $settings): AssetPictures
    {
        return new AssetPictures(
            ConfiguredVariants::from($settings, $this->environment),
            $this->devMode,
            $this->warn,
            $settings->lazysrcByDefault ? Loading::Lazysrc : Loading::Lazy,
            self::alternates($settings),
        );
    }
}
