<?php

namespace parallelogram\images\console\controllers;

use craft\elements\Asset;
use parallelogram\images\Plugin;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Force-rebuilds an asset's variants: deletes its transforms then regenerates,
 * for when a transform was written corrupt/truncated and idempotent warming
 * would otherwise skip it.
 *
 *   php craft p-images/rebuild --asset=13244
 *   php craft p-images/rebuild --volume=product
 */
class RebuildController extends Controller
{
    public $defaultAction = 'index';

    /** @var int|null A single asset id. */
    public ?int $asset = null;

    /** @var string|null Rebuild every image in this volume. */
    public ?string $volume = null;

    public function actionIndex(): int
    {
        $warmer = Plugin::getInstance()->warmer;

        if (null !== $this->asset) {
            $asset = Asset::find()->id($this->asset)->kind('image')->one();
            if (null === $asset) {
                $this->stderr("No image asset with id {$this->asset}.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $made = $warmer->rebuild($asset);
            $this->stdout("Rebuilt {$asset->filename} — {$made} variants.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        if (null !== $this->volume) {
            $ids   = Asset::find()->volume($this->volume)->kind('image')->ids();
            $total = count($ids);
            if (0 === $total) {
                $this->stdout("No image assets in volume “{$this->volume}”.\n");
                return ExitCode::OK;
            }

            $this->stdout("Rebuilding {$total} asset(s)...\n\n");
            $done = 0;

            foreach (array_chunk($ids, 100) as $chunk) {
                foreach (Asset::find()->id($chunk)->kind('image')->all() as $asset) {
                    $made = $warmer->rebuild($asset);
                    $done++;
                    $this->stdout(sprintf("[%d/%d] %s — %d variants\n", $done, $total, $asset->filename, $made));
                }
                gc_collect_cycles();
            }

            $this->stdout("\nDone.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stderr("Specify --asset=<id> or --volume=<handle>.\n", Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['asset', 'volume']);
    }
}
