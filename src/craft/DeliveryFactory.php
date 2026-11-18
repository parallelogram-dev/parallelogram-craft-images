<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\backends\BackendAvailability;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\VolumeRoutingGenerator;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\delivery\DeliveryPorts;
use parallelogram\images\delivery\VariantRequestHandler;
use parallelogram\images\models\Settings;
use parallelogram\images\processing\gd\GdGenerator;
use parallelogram\images\processing\ImagickGenerator;
use parallelogram\images\processing\vips\VipsGenerator;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use yii\base\Exception;

/**
 * Builds the variant request handler from the plugin settings, or nothing
 * when variants aren't fully configured: no filesystem or prefix chosen, an
 * unknown filesystem, no secret, invalid settings, or no site to take URLs
 * from. Without a handler every
 * variant request is answered "not found".
 *
 * @internal
 */
final readonly class DeliveryFactory
{
    public function __construct(
        private CraftEnvironmentInterface $environment,
    ) {}

    public function handler(Settings $settings): ?VariantRequestHandler
    {
        $filesystem = $settings->isConfigured() ? $this->environment->filesystem($settings->variantFilesystem) : null;

        if (null === $filesystem) {
            return null;
        }

        try {
            $processing = new Processing($settings->autoOrient, $settings->convertToSrgb, $settings->sharpenAmount);
            $path       = new VariantPath($this->environment->parse($settings->variantPrefix));
            $hasher     = new VariantHasher($this->environment->parse('$' . SecretProvisioner::ENV_NAME), $processing);

            return new VariantRequestHandler(
                $path,
                new VariantPlanner($hasher, $path, $settings->siteQuality()),
                $settings->catalogue(),
                new DeliveryPorts(
                    new CraftSourceLookup($this->environment->findAsset(...)),
                    FsVariantStore::forFilesystem($filesystem, $this->environment->siteUrl()),
                    $this->generator($settings, $processing),
                    new MutexVariantLock($this->environment->mutex()),
                ),
            );
        } catch (InvalidVariantException|InvalidSizeSetException|InvalidBackendException|Exception) {
            return null;
        }
    }

    /**
     * @throws InvalidBackendException
     */
    private function generator(Settings $settings, Processing $processing): VolumeRoutingGenerator
    {
        $files = new CraftSourceFile($this->environment->findAsset(...));

        return new VolumeRoutingGenerator(
            BackendConfig::choice($settings->backends),
            new BackendAvailability($this->environment->hasLibrary(...)),
            [
                BackendKind::Vips->value    => new VipsGenerator($files, $processing),
                BackendKind::Imagick->value => new ImagickGenerator($files, $processing),
                BackendKind::Gd->value      => new GdGenerator($files, $processing),
            ],
            $this->environment->volumeOf(...),
        );
    }
}
