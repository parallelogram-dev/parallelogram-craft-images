<?php

declare(strict_types=1);

namespace parallelogram\images\config;

/**
 * The result of converting the prototype's settings: new size set configs,
 * field assignments, and notes on anything that couldn't carry over exactly.
 *
 * @api
 */
final readonly class PrototypeConversion
{
    /**
     * @param array<string, array<string, mixed>> $sizeSets
     * @param list<array{fieldUid: string, layoutUid: null, sizeSets: list<string>}> $fieldAssignments
     * @param list<string> $notes
     */
    public function __construct(
        public array $sizeSets,
        public array $fieldAssignments,
        public array $notes,
    ) {}
}
