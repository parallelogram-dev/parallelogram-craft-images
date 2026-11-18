<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

use RuntimeException;

/**
 * A variant couldn't be made, e.g. the original file is missing or the image
 * library failed.
 *
 * @api
 */
final class GenerationFailedException extends RuntimeException {}
