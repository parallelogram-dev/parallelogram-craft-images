<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\backends\BackendAvailability;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\VolumeRoutingGenerator;
use parallelogram\images\delivery\DeliveryPorts;
use parallelogram\images\delivery\VariantRequestHandler;
use parallelogram\images\models\Settings;
use parallelogram\images\processing\gd\GdGenerator;
use parallelogram\images\processing\ImagickGenerator;
use parallelogram\images\processing\vips\VipsGenerator;

/**
 * Builds the variant request handler, or nothing until variants are fully
 * configured (see {@see ConfiguredVariants}). Without a handler every variant
 * request is answered "not found".
 *
 * @internal
 */
final readonly class DeliveryFactory
{
    public function __construct(
        private CraftEnvironmentInterface $environment,
    ) {}

    public function generatorFor(VariantBackends $backends): VolumeRoutingGenerator
    {
        $files = new CraftSourceFile($this->environment->findAsset(...));

        return new VolumeRoutingGenerator(
            $backends->choice,
            new BackendAvailability($this->environment->hasLibrary(...)),
            [
                BackendKind::Vips->value    => new VipsGenerator($files, $backends->processing),
                BackendKind::Imagick->value => new ImagickGenerator($files, $backends->processing),
                BackendKind::Gd->value      => new GdGenerator($files, $backends->processing),
            ],
            $this->environment->volumeOf(...),
        );
    }

    public function handler(Settings $settings): ?VariantRequestHandler
    {
        $variants = ConfiguredVariants::from($settings, $this->environment);

        if (null === $variants) {
            return null;
        }

        return new VariantRequestHandler(
            $variants->path,
            $variants->planner,
            $variants->catalogue,
            new DeliveryPorts(
                new CraftSourceLookup($this->environment->findAsset(...)),
                $variants->storage->store,
                $this->generatorFor($variants->backends),
                new MutexVariantLock($this->environment->mutex()),
            ),
        );
    }
}
