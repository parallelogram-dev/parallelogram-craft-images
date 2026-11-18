<?php

declare(strict_types=1);

namespace parallelogram\images\variants;

use InvalidArgumentException;

/**
 * A source image, path prefix or secret that variants cannot be built from.
 *
 * @api
 */
final class InvalidVariantException extends InvalidArgumentException {}
