<?php

declare(strict_types=1);

namespace parallelogram\images\test\Trait;

use craft\db\Query;
use craft\db\Table;

/**
 * Counts jobs waiting in Craft's queue by their description.
 *
 * @internal
 */
trait CountsQueuedJobsTrait
{
    private static function queuedJobs(string $description): int
    {
        return (int) (new Query())->from(Table::QUEUE)
            ->where(['description' => $description])
            ->count();
    }
}
