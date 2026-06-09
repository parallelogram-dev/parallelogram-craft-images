<?php

namespace parallelogram\imagevariants\console\controllers;

use parallelogram\imagevariants\Plugin;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Reports variant coverage without generating anything — a read-only check that
 * every assigned transform exists on disk. Use as a pre-deploy guard: exits
 * non-zero when variants are missing, so CI can fail the build.
 *
 *   php craft image-variants/audit
 *   php craft image-variants/audit --field=preview --matrix=card
 */
class AuditController extends Controller
{
    public $defaultAction = 'index';

    /** @var string|null Limit to a single Asset field handle. */
    public ?string $field = null;

    /** @var string|null Limit to a single matrix handle. */
    public ?string $matrix = null;

    /** @var string|null Limit to a single volume handle. */
    public ?string $volume = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['field', 'matrix', 'volume']);
    }

    public function actionIndex(): int
    {
        $this->stdout("Auditing variant coverage (no generation)...\n\n");

        $report = Plugin::getInstance()->warmer->audit($this->field, $this->matrix, $this->volume);

        if ($report === []) {
            $this->stdout("Nothing assigned to audit.\n");
            return ExitCode::OK;
        }

        $missingTotal = 0;

        foreach ($report as $matrix => $row) {
            $missingTotal += $row['missing'];
            $complete = $row['missing'] === 0;

            $this->stdout(sprintf(
                "%-12s %d/%d assets fully warmed, %d/%d transforms missing\n",
                $matrix,
                $row['covered'],
                $row['assets'],
                $row['missing'],
                $row['transforms']
            ), $complete ? Console::FG_GREEN : Console::FG_YELLOW);
        }

        $this->stdout("\n");

        if ($missingTotal === 0) {
            $this->stdout("All variants present — no warming needed.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout("{$missingTotal} transform(s) missing — run image-variants/warm.\n", Console::FG_YELLOW);

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
