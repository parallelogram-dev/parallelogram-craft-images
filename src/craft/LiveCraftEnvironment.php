<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Craft;
use craft\base\FsInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use Override;
use yii\base\Exception;
use yii\base\InvalidConfigException;
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
    public function extensionLoaded(string $extension): bool
    {
        return '' !== $extension && extension_loaded($extension);
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

    #[Override]
    public function siteUrl(): string
    {
        try {
            return UrlHelper::siteUrl();
        } catch (Exception) {
            return '';
        }
    }

    #[Override]
    public function volumeOf(string $assetUid): ?string
    {
        try {
            return $this->findAsset($assetUid)?->getVolume()->uid;
        } catch (InvalidConfigException) {
            return null;
        }
    }
}
