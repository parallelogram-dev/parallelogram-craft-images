<?php

declare(strict_types=1);

namespace parallelogram\images\console\controllers;

use craft\console\Controller;
use Override;
use parallelogram\images\craft\WarmingCommands;
use parallelogram\images\Plugin;

/**
 * Makes every image's missing variants ahead of time. Variants already there
 * are left alone, so running it again carries on where it stopped.
 *
 *     php craft p-images/warm
 *     php craft p-images/warm --volume=products --size-set=card
 *
 * @api
 */
final class WarmController extends Controller
{
    use PrintsOutcomeTrait;

    /**
     * @var string|null Only make this size set's variants.
     */
    public ?string $sizeSet = null;

    /**
     * @var string|null Only images in the volume with this handle.
     */
    public ?string $volume = null;

    public function actionIndex(): int
    {
        $commands = new WarmingCommands(Plugin::getInstance()?->warming());

        return $this->print($commands->warm($this->volume, $this->sizeSet, $this->progress(...)));
    }

    /**
     * @param string $actionID
     *
     * @return list<string>
     */
    #[Override]
    public function options($actionID): array
    {
        return [...parent::options($actionID), 'sizeSet', 'volume'];
    }
}
