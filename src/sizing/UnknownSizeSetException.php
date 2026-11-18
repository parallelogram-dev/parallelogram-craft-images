<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use OutOfBoundsException;

/**
 * A template or assignment asked for a size set that does not exist.
 *
 * @api
 */
final class UnknownSizeSetException extends OutOfBoundsException {}
