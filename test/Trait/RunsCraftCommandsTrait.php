<?php

declare(strict_types=1);

namespace parallelogram\images\test\Trait;

use Craft;
use RuntimeException;

use function getenv;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

/**
 * Runs `php craft …` as a separate process against the test database. Craft
 * prints straight to the terminal, so console commands can't be run in the
 * test process without writing to stdout.
 *
 * @internal
 */
trait RunsCraftCommandsTrait
{
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
            "php craft {$arguments}",
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
}
