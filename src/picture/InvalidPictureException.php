<?php

declare(strict_types=1);

namespace parallelogram\images\picture;

use InvalidArgumentException;

/**
 * A template passed options `asset.picture()` can't use.
 *
 * @api
 */
final class InvalidPictureException extends InvalidArgumentException {}
