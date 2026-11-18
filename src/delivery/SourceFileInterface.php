<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

use parallelogram\images\variants\SourceImage;

/**
 * Reads an original image file.
 *
 * @api
 */
interface SourceFileInterface
{
    /**
     * @throws GenerationFailedException When the file can't be read.
     */
    public function contents(SourceImage $source): string;
}
