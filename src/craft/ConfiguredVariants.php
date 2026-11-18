<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use parallelogram\images\backends\InvalidBackendException;
use parallelogram\images\backends\RemoteSettings;
use parallelogram\images\config\BackendConfig;
use parallelogram\images\models\Settings;
use parallelogram\images\picture\StoredVariantLinks;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use yii\base\Exception;

/**
 * Everything variants need, built once from the plugin settings, which
 * both variant delivery and `asset.picture()` share. There is none until
 * variants are fully configured: no filesystem or prefix chosen, an unknown
 * filesystem, no secret, invalid settings, or no site to take URLs from.
 *
 * @internal
 */
final readonly class ConfiguredVariants
{
    public function __construct(
        public SizeSetCatalogue $catalogue,
        public VariantPath $path,
        public VariantPlanner $planner,
        public VariantStorage $storage,
        public VariantBackends $backends,
    ) {}

    public static function from(Settings $settings, CraftEnvironmentInterface $environment): ?self
    {
        $filesystem = $settings->isConfigured() ? $environment->filesystem($settings->variantFilesystem) : null;

        if (null === $filesystem) {
            return null;
        }

        try {
            $processing = new Processing(
                $settings->autoOrient,
                $settings->convertToSrgb,
                $settings->sharpenAmount,
                $settings->avifEffort,
            );
            $path    = new VariantPath($environment->parse($settings->variantPrefix));
            $hasher  = new VariantHasher($environment->parse('$' . SecretProvisioner::ENV_NAME), $processing);
            $siteUrl = $environment->siteUrl();
            $store   = FsVariantStore::forFilesystem($filesystem, $siteUrl);

            return new self(
                $settings->catalogue(),
                $path,
                new VariantPlanner($hasher, $path, $settings->siteQuality()),
                new VariantStorage($store, new StoredVariantLinks($store, $siteUrl)),
                new VariantBackends(
                    BackendConfig::choice($settings->backends),
                    $processing,
                    self::remote(BackendConfig::remote($settings->backends), $environment),
                ),
            );
        } catch (InvalidVariantException|InvalidSizeSetException|InvalidBackendException|Exception) {
            return null;
        }
    }

    private static function remote(RemoteSettings $stored, CraftEnvironmentInterface $environment): RemoteSettings
    {
        return new RemoteSettings(
            $environment->parse($stored->imgproxyUrl),
            $environment->parse($stored->imgproxyKey),
            $environment->parse($stored->imgproxySalt),
            $environment->parse($stored->cloudflareZoneUrl),
        );
    }
}
