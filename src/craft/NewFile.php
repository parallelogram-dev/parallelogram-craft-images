<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\elements\Asset;
use craft\events\ModelEvent;

/**
 * Whether an asset's save stored a file: a new upload, or a replacement.
 *
 * @internal
 */
final class NewFile
{
    public static function arrived(Asset $asset, ModelEvent $event): bool
    {
        return $event->isNew || Asset::SCENARIO_REPLACE === $asset->getScenario();
    }
}
