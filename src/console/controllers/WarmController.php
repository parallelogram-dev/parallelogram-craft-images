<?php

namespace parallelogram\imagevariants\console\controllers;

use craft\elements\Asset;
use parallelogram\imagevariants\Plugin;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Pre-generates the assigned variants for existing assets.
 *
 * A plain, long-running CLI sweep — no queue. Runs the same Warmer::warm()
 * engine as the CP WarmJob: serial, ~100 assets in memory at a time, idempotent
 * (existing transforms are not re-encoded), so re-running resumes by filling
 * only what is missing.
 *
 *   php craft image-variants/warm
 *   php craft image-variants/warm --field=preview
 *   php craft image-variants/warm --matrix=card --volume=product --limit=100
 *   php craft image-variants/warm --throttle=0.25
 */
class WarmController extends Controller
{
    public $defaultAction = 'index';

    /** @var string|null Limit to a single Asset field handle. */
    public ?string $field = null;

    /** @var string|null Limit to a single matrix handle. */
    public ?string $matrix = null;

    /** @var string|null Limit to a single volume handle. */
    public ?string $volume = null;

    /** @var int|null Cap the number of assets processed. */
    public ?int $limit = null;

    /** @var float Seconds to pause between assets, to cap load. */
    public float $throttle = 0;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['field', 'matrix', 'volume', 'limit', 'throttle']);
    }

    public function actionIndex(): int
    {
        $warmer = Plugin::getInstance()->warmer;

        $plan = $warmer->plan($this->field, $this->matrix, $this->volume);

        if ($this->limit !== null) {
            $plan = array_slice($plan, 0, $this->limit, true);
        }

        $total = count($plan);
        if ($total === 0) {
            $this->stdout("Nothing to warm.\n");
            return ExitCode::OK;
        }

        $this->stdout("Warming {$total} asset(s)...\n\n");

        $variants = $warmer->warm($plan, function(int $processed, int $total, ?Asset $asset, int $made): void {
            $this->stdout(sprintf(
                "[%d/%d] %s — %d variants\n",
                $processed,
                $total,
                $asset?->filename ?? '(missing)',
                $made
            ));
        }, $this->throttle);

        $this->stdout(sprintf(
            "\nDone. %d assets processed, %d variants generated.\n",
            $total,
            $variants
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }
}
