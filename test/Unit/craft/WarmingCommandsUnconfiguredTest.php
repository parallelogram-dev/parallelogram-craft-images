<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use parallelogram\images\craft\CommandOutcome;
use parallelogram\images\craft\WarmingCommands;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WarmingCommands::class)]
#[UsesClass(CommandOutcome::class)]
#[Group('unit')]
final class WarmingCommandsUnconfiguredTest extends TestCase
{
    /**
     * @return array<string, array{callable(WarmingCommands): CommandOutcome}>
     */
    public static function commandProvider(): array
    {
        return [
            'warm'    => [
                static fn(WarmingCommands $commands): CommandOutcome => $commands->warm(
                    volume: null,
                    sizeSet: null,
                ),
            ],
            'audit'   => [
                static fn(WarmingCommands $commands): CommandOutcome => $commands->audit(
                    volume: null,
                    sizeSet: null,
                ),
            ],
            'rebuild' => [static fn(WarmingCommands $commands): CommandOutcome => $commands->rebuild(null)],
            'gc'      => [static fn(WarmingCommands $commands): CommandOutcome => $commands->gc()],
        ];
    }

    /**
     * @param callable(WarmingCommands): CommandOutcome $command
     */
    #[DataProvider('commandProvider')]
    #[Test]
    public function failsWithAHelpfulMessageUntilVariantsAreConfigured(callable $command): void
    {
        static::assertEquals(
            new CommandOutcome(1, [
                'Image variants are off: choose a variant filesystem and prefix in the plugin settings.',
            ]),
            $command(new WarmingCommands(null)),
        );
    }
}
