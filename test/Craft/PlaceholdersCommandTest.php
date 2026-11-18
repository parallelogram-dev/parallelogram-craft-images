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
 * The `p-images/placeholders` command, run as a separate process against the
 * test database. Craft prints straight to the terminal, so it can't be run
 * in this process without writing to stdout.
 *
 * The test database holds nothing committed (every other test rolls back), so
 * the command has no images to work on; this checks it boots, takes its
 * option and reports. It isn't counted in coverage.
 *
 * @internal
 */
#[CoversNothing]
#[Group('integration')]
#[Group('craft')]
final class PlaceholdersCommandTest extends TestCase
{
    use RunsCraftCommandsTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function optionProvider(): array
    {
        return ['by default' => [''], 'when forced' => [' --force']];
    }

    /**
     * @throws RuntimeException
     */
    #[DataProvider('optionProvider')]
    #[Test]
    public function reportsWhatItDid(string $arguments): void
    {
        [$exit, $output, $errors] = self::execute("p-images/placeholders{$arguments}");

        static::assertSame(
            [0, "Placeholder colours: 0 worked out, 0 skipped, 0 failed.\n", ''],
            [$exit, $output, $errors],
        );
    }
}
