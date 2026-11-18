<?php

declare(strict_types=1);

namespace putyourlightson\blitz\services;

/**
 * Records what is added to Blitz's refresh data.
 */
final class RefreshCacheService
{
    /**
     * @var list<array{string, list<int>}>
     */
    public array $added = [];

    /**
     * @param list<int> $elementIds
     */
    public function addElementIds(string $elementType, array $elementIds): void
    {
        $this->added[] = [$elementType, $elementIds];
    }
}
