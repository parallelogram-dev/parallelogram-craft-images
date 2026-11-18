<?php

declare(strict_types=1);

namespace parallelogram\images\console\controllers;

use craft\console\Controller;
use parallelogram\images\craft\WarmingCommands;
use parallelogram\images\Plugin;

/**
 * Removes variants nothing can ask for any more: those of deleted images,
 * and files left behind by replaced images or changed size sets.
 *
 *     php craft p-images/gc
 *
 * @api
 */
final class GcController extends Controller
{
    use PrintsOutcomeTrait;

    public function actionIndex(): int
    {
        return $this->print((new WarmingCommands(Plugin::getInstance()?->warming()))->gc());
    }
}
