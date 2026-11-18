<?php

declare(strict_types=1);

namespace parallelogram\images\events;

use craft\elements\Asset;
use yii\base\Event;

/**
 * Fired on {@see \parallelogram\images\Plugin} once an image's placeholder
 * colour has been stored, so anything that cached a page showing the image
 * (a full-page cache, a CDN) can refresh it.
 *
 * The colour is written straight to the asset's stored content, without
 * saving the element, so Craft's own save events don't fire for it.
 *
 * @api
 */
final class PlaceholderSavedEvent extends Event
{
    /**
     * @param string $colour The colour stored, as `#rrggbb`.
     */
    public function __construct(
        public Asset $asset,
        public string $colour,
    ) {
        parent::__construct();
    }
}
