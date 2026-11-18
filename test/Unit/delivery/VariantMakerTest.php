<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\delivery;

use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\MakeResult;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantMaker;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\test\TestAsset\Factory\VariantFileFactory;
use parallelogram\images\test\TestAsset\Fake\InMemoryVariantStore;
use parallelogram\images\test\TestAsset\Fake\RecordingGenerator;
use parallelogram\images\test\TestAsset\Fake\ScriptedLock;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariantMaker::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(GenerationFailedException::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[Group('unit')]
final class VariantMakerTest extends TestCase
{
    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function fallsBackToAFileMadeWhileWaitingForTheLock(): void
    {
        $store = new InMemoryVariantStore();
        $lock  = new ScriptedLock(
            free: false,
            onAcquire: static function () use ($store): void {
                $store->files['480.jpg'] = 'made elsewhere';
            },
        );
        $generator = new RecordingGenerator();

        static::assertSame(
            [MakeResult::Present, []],
            [
                (new VariantMaker($generator, $store, $lock))->ensure(
                    SourceImageFactory::make(),
                    VariantFileFactory::make(480),
                ),
                $generator->generated,
            ],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function leavesAFileThatIsAlreadyThere(): void
    {
        $store                   = new InMemoryVariantStore();
        $store->files['480.jpg'] = 'old';
        $generator               = new RecordingGenerator();

        static::assertSame(
            [MakeResult::Present, []],
            [
                (new VariantMaker($generator, $store, new ScriptedLock()))->ensure(
                    SourceImageFactory::make(),
                    VariantFileFactory::make(480),
                ),
                $generator->generated,
            ],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function makesAndSavesAMissingFileUnderTheLock(): void
    {
        $store     = new InMemoryVariantStore();
        $lock      = new ScriptedLock();
        $generator = new RecordingGenerator();

        static::assertSame(
            [MakeResult::Made, ['480.jpg' => 'bytes of 480.jpg'], ['480.jpg']],
            [
                (new VariantMaker($generator, $store, $lock))->ensure(
                    SourceImageFactory::make(),
                    VariantFileFactory::make(480),
                ),
                $store->files,
                $lock->released,
            ],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function reportsFailureWhenAnotherRequestHoldsTheLockAndNothingAppears(): void
    {
        static::assertSame(
            MakeResult::Failed,
            (new VariantMaker(new RecordingGenerator(), new InMemoryVariantStore(), new ScriptedLock(free: false)))
                ->ensure(SourceImageFactory::make(), VariantFileFactory::make(480)),
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function reportsFailureWhenTheImageCantBeGeneratedAndStillReleasesTheLock(): void
    {
        $lock = new ScriptedLock();

        static::assertSame(
            [MakeResult::Failed, ['480.jpg']],
            [
                (new VariantMaker(new RecordingGenerator(fails: true), new InMemoryVariantStore(), $lock))->ensure(
                    SourceImageFactory::make(),
                    VariantFileFactory::make(480),
                ),
                $lock->released,
            ],
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function seesAFileAnotherRequestMadeBetweenTheCheckAndTheWrite(): void
    {
        $store = new InMemoryVariantStore();
        $lock  = new ScriptedLock(onAcquire: static function () use ($store): void {
            $store->files['480.jpg'] = 'made elsewhere';
        });
        $generator = new RecordingGenerator();

        static::assertSame(
            [MakeResult::Present, 'made elsewhere', []],
            [
                (new VariantMaker($generator, $store, $lock))->ensure(
                    SourceImageFactory::make(),
                    VariantFileFactory::make(480),
                ),
                $store->files['480.jpg'] ?? '',
                $generator->generated,
            ],
        );
    }
}
