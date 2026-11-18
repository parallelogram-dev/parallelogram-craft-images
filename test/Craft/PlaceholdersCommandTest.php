<?php

declare(strict_types=1);

namespace parallelogram\images\test\Craft;

use Craft;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function getenv;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

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
    /**
     * @return array<string, array{string}>
     */
    public static function optionProvider(): array
    {
        return ['by default' => [''], 'when forced' => [' --force']];
    }

    /**
     * @return array{int, string, string} Exit code, output and error output.
     *
     * @throws RuntimeException When the command can't be started.
     */
    private static function execute(string $arguments): array
    {
        $root    = Craft::getAlias('@root');
        $pipes   = [];
        $process = proc_open(
            "php craft p-images/placeholders{$arguments}",
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            '' === $root ? null : $root,
            ['CRAFT_DB_DATABASE' => 'craft_images_test'] + getenv(),
        );

        [, $stdout, $stderr] = $pipes + [null, null, null];

        if (! is_resource($process) || ! is_resource($stdout) || ! is_resource($stderr)) {
            throw new RuntimeException('Could not start the Craft command.');
        }

        $output = (string) stream_get_contents($stdout);
        $errors = (string) stream_get_contents($stderr);

        return [proc_close($process), $output, $errors];
    }

    /**
     * @throws RuntimeException
     */
    #[Test]
    public function refusesAnOptionItDoesntHave(): void
    {
        [$exit] = self::execute(' --colour-me-surprised');

        static::assertNotSame(0, $exit);
    }

    /**
     * @throws RuntimeException
     */
    #[DataProvider('optionProvider')]
    #[Test]
    public function reportsWhatItDid(string $arguments): void
    {
        [$exit, $output, $errors] = self::execute($arguments);

        static::assertSame(
            [0, "Placeholder colours: 0 worked out, 0 skipped, 0 failed.\n", ''],
            [$exit, $output, $errors],
        );
    }
}
