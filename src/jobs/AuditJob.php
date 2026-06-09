<?php

namespace parallelogram\imagevariants\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use parallelogram\imagevariants\Plugin;

/**
 * Audits variant coverage as a queue job — read-only existence checks, no
 * generation — caching the result for the utility to display.
 */
class AuditJob extends BaseJob
{
    public ?string $field = null;

    public ?string $matrix = null;

    public ?string $volume = null;

    public function execute($queue): void
    {
        Plugin::getInstance()->warmer->audit(
            $this->field,
            $this->matrix,
            $this->volume,
            function(int $processed, int $total, Asset $asset) use ($queue): void {
                $this->setProgress(
                    $queue,
                    $total > 0 ? $processed / $total : 1,
                    Craft::t('image-variants', '{processed} of {total}', [
                        'processed' => $processed,
                        'total' => $total,
                    ])
                );
            },
            true,
        );
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('image-variants', 'Auditing image variant coverage');
    }
}
