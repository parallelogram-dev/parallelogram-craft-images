<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\backends;

use parallelogram\images\backends\BackendAvailability;
use parallelogram\images\backends\BackendChoice;
use parallelogram\images\backends\BackendKind;
use parallelogram\images\backends\VolumeRoutingGenerator;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Mode;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\test\TestAsset\Fake\RecordingGenerator;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function in_array;

/**
 * @internal
 */
#[CoversClass(VolumeRoutingGenerator::class)]
#[UsesClass(BackendAvailability::class)]
#[UsesClass(BackendChoice::class)]
#[UsesClass(BackendKind::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(GenerationFailedException::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[Group('unit')]
final class VolumeRoutingGeneratorTest extends TestCase
{
    private static function file(): VariantFile
    {
        return new VariantFile(
            new Variant('card', new Box(10, null, Mode::Crop), Format::Avif, quality: 60),
            '0123456789abcdef',
            'asset/variant/x.avif',
        );
    }

    /**
     * @param list<string> $extensions
     */
    private static function router(
        BackendChoice $choice,
        array $extensions,
        RecordingGenerator $imagick,
        RecordingGenerator $gd,
    ): VolumeRoutingGenerator {
        return new VolumeRoutingGenerator(
            $choice,
            new BackendAvailability(static fn(string $name): bool => in_array($name, $extensions, strict: true)),
            ['imagick' => $imagick, 'gd' => $gd],
            static fn(): string => 'volume-1',
        );
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function handsTheVariantToTheVolumesGenerator(): void
    {
        $imagick = new RecordingGenerator();
        $gd      = new RecordingGenerator();
        $router  = self::router(
            new BackendChoice(BackendKind::Imagick, ['volume-1' => BackendKind::Gd]),
            ['imagick', 'gd'],
            $imagick,
            $gd,
        );

        $router->generate(SourceImageFactory::make(), self::file());

        static::assertSame([[], ['asset/variant/x.avif']], [$imagick->generated, $gd->generated]);
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function refusesABackendThatIsNotInstalled(): void
    {
        $router = self::router(
            new BackendChoice(BackendKind::Imagick),
            ['gd'],
            new RecordingGenerator(),
            new RecordingGenerator(),
        );

        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('The imagick backend isn\'t available on this server.');

        $router->generate(SourceImageFactory::make(), self::file());
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function refusesARemoteService(): void
    {
        $router = self::router(
            new BackendChoice(BackendKind::Imgproxy),
            [],
            new RecordingGenerator(),
            new RecordingGenerator(),
        );

        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('imgproxy makes images itself');

        $router->generate(SourceImageFactory::make(), self::file());
    }
}
