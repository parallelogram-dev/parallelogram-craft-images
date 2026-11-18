<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

/**
 * What a variant request led to.
 *
 * @api
 */
enum DeliveryOutcome
{
    /** The file is sent in the response. */
    case Content;

    /** No such variant, now or ever. */
    case NotFound;

    /** The file lives elsewhere; send the visitor there. */
    case Redirect;

    /** It couldn't be made right now; try again shortly. */
    case Unavailable;
}
