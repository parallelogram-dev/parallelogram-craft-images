<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Craft;
use craft\base\FsInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use Override;
use parallelogram\images\processing\vips\VipsProbe;
use yii\base\Exception;
use yii\mutex\Mutex;

use function extension_loaded;
use function is_string;

/**
 * The running Craft site, for {@see DeliveryFactory}.
 *
 * @internal
 */
final class LiveCraftEnvironment implements CraftEnvironmentInterface
{
    private static function asAsset(mixed $element): ?Asset
    {
        return $element instanceof Asset ? $element : null;
    }

    #[Override]
    public function filesystem(string $handle): ?FsInterface
    {
        return Craft::$app->getFs()->getFilesystemByHandle((string) App::parseEnv($handle));
    }

    #[Override]
    public function findAsset(string $uid): ?Asset
    {
        return self::asAsset(Asset::find()->uid($uid)->one());
    }

    /**
     * Imagick and GD are PHP extensions; libvips is checked by {@see VipsProbe}.
     */
    #[Override]
    public function hasLibrary(string $name): bool
    {
        if ('vips' === $name) {
            return VipsProbe::forThisServer()->available();
        }

        return '' !== $name && extension_loaded($name);
    }

    #[Override]
    public function mutex(): Mutex
    {
        return Craft::$app->getMutex();
    }

    #[Override]
    public function parse(string $value): string
    {
        $parsed = App::parseEnv($value);

        return is_string($parsed) ? $parsed : '';
    }

    /**
     * @throws Exception When Craft has no site to take the URL from.
     */
    #[Override]
    public function siteUrl(): string
    {
        return UrlHelper::siteUrl();
    }

    #[Override]
    public function volumeOf(string $assetUid): ?string
    {
        $volumeId = $this->findAsset($assetUid)?->volumeId;

        return null === $volumeId ? null : Craft::$app->getVolumes()->getVolumeById($volumeId)?->uid;
    }
}
