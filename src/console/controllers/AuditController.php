<?php

declare(strict_types=1);

namespace parallelogram\images\console\controllers;

use craft\console\Controller;
use Override;
use parallelogram\images\craft\WarmingCommands;
use parallelogram\images\Plugin;

/**
 * Counts the variants every image is missing without making any, and fails
 * when there are some, so it can guard a deploy.
 *
 *     php craft p-images/audit
 *     php craft p-images/audit --volume=products --size-set=card
 *
 * @api
 */
final class AuditController extends Controller
{
    use PrintsOutcomeTrait;

    /**
     * @var string|null Only check this size set's variants.
     */
    public ?string $sizeSet = null;

    /**
     * @var string|null Only images in the volume with this handle.
     */
    public ?string $volume = null;

    public function actionIndex(): int
    {
        $commands = new WarmingCommands(Plugin::getInstance()?->warming());

        return $this->print($commands->audit($this->volume, $this->sizeSet, $this->progress(...)));
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
