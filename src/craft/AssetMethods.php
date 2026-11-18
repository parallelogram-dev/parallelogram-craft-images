<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use Closure;
use craft\elements\Asset;
use parallelogram\craftkit\craft\ElementMethods;
use parallelogram\images\picture\PictureSources;
use parallelogram\images\sizing\UnknownSizeSetException;
use Twig\Markup;

/**
 * The methods templates call on assets: `asset.picture('handle', {...})`
 * and `asset.variants('handle')`.
 *
 * @internal
 */
final class AssetMethods
{
    /**
     * @param Closure(): AssetPictures $pictures Asked on every call, so new settings take effect straight away.
     */
    public static function register(Closure $pictures): void
    {
        ElementMethods::add(
            Asset::class,
            name: 'picture',
            method: /**
             * @param array<array-key, mixed> $options
             *
             * @throws UnknownSizeSetException In dev mode, for a size set that doesn't exist.
             */
            static fn(Asset $asset, string $sizeSet, array $options = []): Markup => new Markup(
                $pictures()->picture($asset, $sizeSet, $options),
                charset: 'UTF-8',
            ),
        );

        ElementMethods::add(
            Asset::class,
            name: 'variants',
            method: /**
             * @throws UnknownSizeSetException In dev mode, for a size set that doesn't exist.
             */ static fn(Asset $asset, string $sizeSet): ?PictureSources => $pictures()->variants($asset, $sizeSet),
        );
    }
}
