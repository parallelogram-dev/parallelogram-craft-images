<?php

declare(strict_types=1);

namespace parallelogram\images\backends;

use InvalidArgumentException;

/**
 * Backend settings that can't be used. The message is written for the person
 * editing the settings.
 *
 * @api
 */
final class InvalidBackendException extends InvalidArgumentException {}
