<?php

declare(strict_types=1);

namespace parallelogram\images\craft;

/**
 * What a console command should print and its exit code.
 *
 * @internal
 */
final readonly class CommandOutcome
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        public int $exit,
        public array $lines,
    ) {}
}
