<?php

declare(strict_types=1);

namespace parallelogram\images\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use parallelogram\images\craft\CommandOutcome;
use yii\console\ExitCode;

/**
 * Printing for the warming commands: a progress line every so often, then
 * the outcome.
 *
 * @internal
 *
 * @require-extends Controller
 */
trait PrintsOutcomeTrait
{
    private function print(CommandOutcome $outcome): int
    {
        $colour = ExitCode::OK === $outcome->exit ? Console::FG_GREEN : Console::FG_RED;

        foreach ($outcome->lines as $line) {
            $this->stdout("{$line}\n", $colour);
        }

        return $outcome->exit;
    }

    private function progress(int $done, int $total): void
    {
        if (0 === ($done % 25) || $done === $total) {
            $this->stdout("[{$done}/{$total}]\n");
        }
    }
}
