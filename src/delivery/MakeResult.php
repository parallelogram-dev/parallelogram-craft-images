<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

/**
 * What happened when a variant file was asked for.
 *
 * @api
 */
enum MakeResult
{
    /**
     * Generated and saved by this request.
     */
    case Made;

    /**
     * Already there, or made by another request that held the lock.
     */
    case Present;

    /**
     * Couldn't be generated.
     */
    case Failed;
}
