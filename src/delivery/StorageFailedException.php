<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

use RuntimeException;

/**
 * The variant filesystem couldn't be read or written.
 *
 * @api
 */
final class StorageFailedException extends RuntimeException {}
