<?php

declare(strict_types=1);

namespace parallelogram\images\warming;

/**
 * What a sweep removed: whole folders of assets that no longer exist as
 * resizable images, and folders of out-of-date variants of those that do.
 *
 * @api
 */
final readonly class SweepResult
{
    public function __construct(
        public int $assetFolders = 0,
        public int $staleFolders = 0,
        public int $checkedAssets = 0,
    ) {}
}
