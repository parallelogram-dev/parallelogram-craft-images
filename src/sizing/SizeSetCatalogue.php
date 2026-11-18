<?php

declare(strict_types=1);

namespace parallelogram\images\sizing;

use function array_key_exists;
use function array_keys;
use function array_values;

/**
 * Every size set defined for a site, looked up by handle.
 *
 * @api
 */
final readonly class SizeSetCatalogue
{
    /**
     * @var array<string, SizeSet>
     */
    private array $sizeSets;

    /**
     * @param list<SizeSet> $sizeSets
     *
     * @throws InvalidSizeSetException When two size sets share a handle.
     */
    public function __construct(array $sizeSets)
    {
        $byHandle = [];

        foreach ($sizeSets as $sizeSet) {
            if (array_key_exists($sizeSet->handle, $byHandle)) {
                throw new InvalidSizeSetException("Two size sets use the handle “{$sizeSet->handle}”.");
            }

            $byHandle[$sizeSet->handle] = $sizeSet;
        }

        $this->sizeSets = $byHandle;
    }

    /**
     * @return list<SizeSet>
     */
    public function all(): array
    {
        return array_values($this->sizeSets);
    }

    /**
     * @throws UnknownSizeSetException When no size set has the handle.
     */
    public function get(string $handle): SizeSet
    {
        return (
            $this->sizeSets[$handle] ?? throw new UnknownSizeSetException("There is no size set called “{$handle}”.")
        );
    }

    /**
     * @return list<string>
     */
    public function handles(): array
    {
        return array_keys($this->sizeSets);
    }

    public function has(string $handle): bool
    {
        return array_key_exists($handle, $this->sizeSets);
    }
}
