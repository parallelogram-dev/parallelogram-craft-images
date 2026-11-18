<?php

declare(strict_types=1);

namespace parallelogram\images\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use Override;
use parallelogram\images\craft\PlaceholderBackfill;
use parallelogram\images\craft\PlaceholderMaker;
use yii\console\ExitCode;

/**
 * Fills in placeholder colours for images already uploaded.
 *
 * @api
 */
final class PlaceholdersController extends Controller
{
    /**
     * @var bool Work every colour out again, not only missing ones.
     */
    public bool $force = false;

    /**
     * Works out the placeholder colour of every image in a volume with a
     * Placeholder Colour field.
     */
    public function actionIndex(): int
    {
        $backfill = new PlaceholderBackfill(PlaceholderMaker::forThisServer(), $this->force);
        $complete = $backfill->run();

        foreach ($backfill->failures() as $failure) {
            $this->stderr("{$failure}\n", Console::FG_RED);
        }

        $this->stdout("{$backfill->summary()}\n", Console::FG_GREEN);

        return $complete ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * @param string $actionID
     *
     * @return list<string>
     */
    #[Override]
    public function options($actionID): array
    {
        return [...parent::options($actionID), 'force'];
    }
}
