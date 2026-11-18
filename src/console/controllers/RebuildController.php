<?php

declare(strict_types=1);

namespace parallelogram\images\console\controllers;

use craft\console\Controller;
use Override;
use parallelogram\images\craft\WarmingCommands;
use parallelogram\images\Plugin;

/**
 * Deletes every variant of each image and makes the assigned ones again.
 *
 *     php craft p-images/rebuild
 *     php craft p-images/rebuild --volume=products
 *
 * @api
 */
final class RebuildController extends Controller
{
    use PrintsOutcomeTrait;

    /**
     * @var string|null Only images in the volume with this handle.
     */
    public ?string $volume = null;

    public function actionIndex(): int
    {
        $commands = new WarmingCommands(Plugin::getInstance()?->warming());

        return $this->print($commands->rebuild($this->volume, $this->progress(...)));
    }

    /**
     * @param string $actionID
     *
     * @return list<string>
     */
    #[Override]
    public function options($actionID): array
    {
        return [...parent::options($actionID), 'volume'];
    }
}
