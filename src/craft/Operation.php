<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

/**
 * What a run over many images does. Each case's value is how a command
 * describes it afterwards, e.g. "Warmed 12 images".
 *
 * @internal
 */
enum Operation: string
{
    case Audit   = 'Checked';
    case Rebuild = 'Rebuilt';
    case Warm    = 'Warmed';
}
