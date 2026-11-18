<?php

declare(strict_types=1);

namespace parallelogram\images\delivery;

/**
 * Lists and removes folders on the variant filesystem, for cleaning up
 * variants that are no longer wanted.
 *
 * @api
 */
interface VariantFoldersInterface
{
    /**
     * Removes a folder and everything in it. Nothing happens when it isn't
     * there.
     *
     * @throws StorageFailedException
     */
    public function deleteFolder(string $path): void;

    /**
     * The names of the folders directly inside a folder, none when it isn't
     * there.
     *
     * @return list<string>
     *
     * @throws StorageFailedException
     */
    public function folders(string $path): array;
}
