<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use InvalidArgumentException;

/**
 * A size set, field assignment or quality value that cannot be used. The
 * message is written for the person editing the settings.
 *
 * @api
 */
final class InvalidSizeSetException extends InvalidArgumentException {}
