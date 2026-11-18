<?php

namespace parallelogram\images\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use parallelogram\images\Plugin;

/**
 * Deliberate, controlled warm as a queue job — so the CP shows native progress
 * (like Feed Me). It runs the same serial, memory-bounded Warmer::warm() as the
 * CLI; this is not the lazy/render path, which still never queues.
 */
class WarmJob extends BaseJob
{
    public ?string $field = null;

    public ?string $matrix = null;

    public ?string $volume = null;

    public function execute($queue): void
    {
        $warmer = Plugin::getInstance()->warmer;
        $plan   = $warmer->plan($this->field, $this->matrix, $this->volume);

        if ([] === $plan) {
            return;
        }

        $scope = ['field' => $this->field, 'matrix' => $this->matrix, 'volume' => $this->volume];

        $warmer->warm($plan, $scope, function (int $processed, int $total, ?Asset $asset, int $made) use (
            $queue,
        ): void {
            $this->setProgress(
                $queue,
                $processed / $total,
                Craft::t('p-images', '{processed} of {total}', [
                    'processed' => $processed,
                    'total'     => $total,
                ]),
            );
        });
    }

    protected function defaultDescription(): ?string
    {
        $scope = array_filter([$this->matrix, $this->field, $this->volume]);

        return (
            Craft::t('p-images', 'Warming image variants')
                . ([] !== $scope ? ' (' . implode(' / ', $scope) . ')' : '')
        );
    }
}
