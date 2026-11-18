<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\delivery\VariantMaker;
use parallelogram\images\models\Settings;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\warming\VariantCleaner;
use parallelogram\images\warming\VariantWarmer;

/**
 * Builds {@see Warming} from the plugin settings, or nothing until variants
 * are fully configured.
 *
 * @internal
 */
final readonly class WarmingFactory
{
    public function __construct(
        private CraftEnvironmentInterface $environment,
    ) {}

    public function warming(Settings $settings): ?Warming
    {
        $variants = ConfiguredVariants::from($settings, $this->environment);

        if (null === $variants) {
            return null;
        }

        try {
            $assignments = $settings->assignments();
        } catch (InvalidSizeSetException) {
            return null;
        }

        $store = $variants->storage->store;
        $maker = new VariantMaker(
            (new DeliveryFactory($this->environment))->generatorFor($variants->backends),
            $store,
            new MutexVariantLock($this->environment->mutex()),
        );

        return new Warming(
            new SizeSetScope($assignments),
            new VariantWarmer($variants->planner, $variants->catalogue, $maker, $store),
            new VariantCleaner($variants->path, $variants->planner, $variants->catalogue, $store),
            $variants->backends,
            new CraftSourceLookup($this->environment->findAsset(...)),
        );
    }
}
