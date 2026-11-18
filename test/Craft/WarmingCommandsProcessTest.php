<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use parallelogram\images\test\Trait\RunsCraftCommandsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The `p-images/warm`, `audit`, `rebuild` and `gc` commands, run as separate
 * processes against the test database, where variants aren't configured.
 * What the commands do is tested in {@see WarmingToolsTest}; this checks they
 * are wired up, take their options and report failure with an exit code.
 *
 * @internal
 */
#[CoversNothing]
#[Group('integration')]
#[Group('craft')]
final class WarmingCommandsProcessTest extends TestCase
{
    use RunsCraftCommandsTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function commandProvider(): array
    {
        return [
            'warm'                  => ['p-images/warm'],
            'warm with options'     => ['p-images/warm --volume=testImages --size-set=card'],
            'audit'                 => ['p-images/audit'],
            'audit with options'    => ['p-images/audit --volume=testImages --size-set=card'],
            'rebuild'               => ['p-images/rebuild'],
            'rebuild with a volume' => ['p-images/rebuild --volume=testImages'],
            'gc'                    => ['p-images/gc'],
        ];
    }

    /**
     * @throws RuntimeException
     */
    #[DataProvider('commandProvider')]
    #[Test]
    public function failsWithAHelpfulMessageWhileVariantsAreOff(string $command): void
    {
        [$exit, $output, $errors] = self::execute($command);

        static::assertSame(
            [1, "Image variants are off: choose a variant filesystem and prefix in the plugin settings.\n", ''],
            [$exit, $output, $errors],
        );
    }

    /**
     * @throws RuntimeException
     */
    #[Test]
    public function refusesAnOptionACommandDoesntHave(): void
    {
        [$exit] = self::execute('p-images/gc --volume=testImages');

        static::assertNotSame(0, $exit);
    }
}
