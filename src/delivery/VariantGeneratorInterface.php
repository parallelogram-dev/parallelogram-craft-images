<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;

/**
 * Makes one variant of a source image and returns the encoded file.
 *
 * @api
 */
interface VariantGeneratorInterface
{
    /**
     * @throws GenerationFailedException
     */
    public function generate(SourceImage $source, VariantFile $file): string;
}
