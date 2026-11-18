<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\backends;

use parallelogram\images\backends\BackendAvailability;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

/**
 * @internal
 */
#[CoversClass(BackendKind::class)]
#[CoversClass(BackendChoice::class)]
#[CoversClass(BackendAvailability::class)]
#[Group('unit')]
final class BackendsTest extends TestCase
{
    /**
     * @return array<string, array{BackendKind, list<string>, BackendKind|null}>
     */
    public static function availabilityProvider(): array
    {
        return [
            'auto with imagick'    => [BackendKind::Auto, ['imagick', 'gd'], BackendKind::Imagick],
            'auto with only gd'    => [BackendKind::Auto, ['gd'], BackendKind::Gd],
            'auto with neither'    => [BackendKind::Auto, [], null],
            'imagick missing'      => [BackendKind::Imagick, ['gd'], null],
            'gd present'           => [BackendKind::Gd, ['gd'], BackendKind::Gd],
            'remote needs nothing' => [BackendKind::Imgproxy, [], BackendKind::Imgproxy],
        ];
    }

    #[Test]
    public function knowsWhichBackendsAreRemote(): void
    {
        static::assertSame(
            [false, true, false, false, true],
            [
                BackendKind::Auto->isRemote(),
                BackendKind::Cloudflare->isRemote(),
                BackendKind::Gd->isRemote(),
                BackendKind::Imagick->isRemote(),
                BackendKind::Imgproxy->isRemote(),
            ],
        );
    }

    #[Test]
    public function letsVolumesOverrideTheSiteBackend(): void
    {
        $choice = new BackendChoice(BackendKind::Imagick, ['volume-1' => BackendKind::Imgproxy]);

        static::assertSame(
            [BackendKind::Imgproxy, BackendKind::Imagick, BackendKind::Imagick],
            [$choice->forVolume('volume-1'), $choice->forVolume('volume-2'), $choice->forVolume(null)],
        );
    }

    #[Test]
    public function reportsWhichBackendsAreInUse(): void
    {
        $choice = new BackendChoice(BackendKind::Auto, ['volume-1' => BackendKind::Imgproxy]);

        static::assertSame(
            [true, true, false],
            [
                $choice->uses(BackendKind::Auto),
                $choice->uses(BackendKind::Imgproxy),
                $choice->uses(BackendKind::Cloudflare),
            ],
        );
    }

    /**
     * @param list<string> $extensions
     */
    #[DataProvider('availabilityProvider')]
    #[Test]
    public function resolvesWhatActuallyRuns(BackendKind $chosen, array $extensions, ?BackendKind $expected): void
    {
        $availability = new BackendAvailability(static fn(string $name): bool => in_array(
            $name,
            $extensions,
            strict: true,
        ));

        static::assertSame($expected, $availability->resolve($chosen));
    }
}
