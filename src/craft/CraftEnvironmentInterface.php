<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

use craft\base\FsInterface;
use craft\elements\Asset;
use yii\base\Exception;
use yii\mutex\Mutex;

/**
 * The parts of a running Craft site that variant delivery needs, so the
 * wiring can be tested without one.
 *
 * @internal
 */
interface CraftEnvironmentInterface
{
    public function extensionLoaded(string $extension): bool;

    public function filesystem(string $handle): ?FsInterface;

    public function findAsset(string $uid): ?Asset;

    public function mutex(): Mutex;

    /**
     * A setting with environment variables and aliases resolved.
     */
    public function parse(string $value): string;

    /**
     * @throws Exception When there is no site to take the URL from.
     */
    public function siteUrl(): string;

    public function volumeOf(string $assetUid): ?string;
}
