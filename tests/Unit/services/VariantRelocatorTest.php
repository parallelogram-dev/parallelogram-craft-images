<?php

declare(strict_types=1);

namespace parallelogram\imagevariants\test\Unit\services;

use craft\base\FsInterface;
use craft\elements\Asset;
use craft\models\Volume;
use craft\models\VolumeFolder;
use craft\services\Assets as AssetsService;
use parallelogram\imagevariants\services\Generator;
use parallelogram\imagevariants\services\PathBuilder;
use parallelogram\imagevariants\services\VariantRelocator;
use parallelogram\imagevariants\services\Warmer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function in_array;

#[CoversClass(VariantRelocator::class)]
#[Group('unit')]
#[Group('service')]
final class VariantRelocatorTest extends TestCase
{
    private const VOLUME_ID     = 3;
    private const NEW_FOLDER_ID = 42;
    private const TRANSFORMS    = [['width' => 640], ['width' => 1280]];

    private PathBuilder&MockObject $paths;

    private Generator&MockObject $generator;

    private Warmer&MockObject $warmer;

    private AssetsService&MockObject $assets;

    private FsInterface&MockObject $fs;

    private VariantRelocator $relocator;

    /** @var list<string> */
    private array $filesOnDisk = [];

    protected function setUp(): void
    {
        $this->paths     = $this->createMock(PathBuilder::class);
        $this->generator = $this->createMock(Generator::class);
        $this->warmer    = $this->createMock(Warmer::class);
        $this->assets    = $this->createMock(AssetsService::class);
        $this->fs        = $this->createMock(FsInterface::class);

        $this->paths->method('uriFor')
            ->willReturnCallback(static fn(Asset $asset, array $t): string => "old/{$t['width']}/hero.jpg");
        $this->paths->method('uriAt')
            ->willReturnCallback(
                static fn(Asset $asset, array $t, string $dir, string $file): string => "{$dir}{$t['width']}/{$file}"
            );
        $this->fs->method('fileExists')
            ->willReturnCallback(fn(string $uri): bool => in_array($uri, $this->filesOnDisk, true));

        $this->warmer->method('matrixHandlesForVolume')->willReturn(['product']);
        $this->generator->method('transformsFor')->with(['product'])->willReturn(self::TRANSFORMS);
        $this->assets->method('getFolderById')
            ->willReturnCallback(fn(int $id): ?VolumeFolder => $id === self::NEW_FOLDER_ID
                ? $this->makeFolder('new-slug/', self::VOLUME_ID)
                : null);

        $this->relocator = new VariantRelocator($this->paths, $this->generator, $this->warmer, $this->assets);
    }

    public function testCopiesEachExistingVariantToTheNewLocation(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg', 'old/1280/hero.jpg'];

        $copies = [];
        $this->fs->expects($this->exactly(2))
            ->method('copyFile')
            ->willReturnCallback(static function (string $from, string $to) use (&$copies): void {
                $copies[] = [$from, $to];
            });

        $copied = $this->relocator->copyToNewLocation($this->makeMovingAsset(), false);

        self::assertSame(2, $copied);
        self::assertSame(
            [['old/640/hero.jpg', 'new-slug/640/hero.jpg'], ['old/1280/hero.jpg', 'new-slug/1280/hero.jpg']],
            $copies
        );
    }

    public function testMapsARenamedFileToItsNewFilename(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg'];

        $this->fs->expects($this->once())->method('copyFile')->with('old/640/hero.jpg', 'new-slug/640/renamed.jpg');

        $this->relocator->copyToNewLocation($this->makeMovingAsset('renamed.jpg'), false);
    }

    public function testDoesNotOverwriteAVariantAlreadyAtTheNewLocation(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg', 'old/1280/hero.jpg', 'new-slug/640/hero.jpg'];

        $this->fs->expects($this->once())->method('copyFile')->with('old/1280/hero.jpg', 'new-slug/1280/hero.jpg');

        self::assertSame(1, $this->relocator->copyToNewLocation($this->makeMovingAsset(), false));
    }

    public function testSkipsVariantsThatWereNeverGenerated(): void
    {
        $this->filesOnDisk = ['old/1280/hero.jpg'];

        $this->fs->expects($this->once())->method('copyFile')->with('old/1280/hero.jpg', 'new-slug/1280/hero.jpg');

        self::assertSame(1, $this->relocator->copyToNewLocation($this->makeMovingAsset(), false));
    }

    public function testLeavesVariantsWhoseLocationIsUnchanged(): void
    {
        $paths = $this->createMock(PathBuilder::class);
        $paths->method('uriFor')->willReturn('same/640/hero.jpg');
        $paths->method('uriAt')->willReturn('same/640/hero.jpg');
        $this->filesOnDisk = ['same/640/hero.jpg'];

        $this->fs->expects($this->never())->method('copyFile');

        $relocator = new VariantRelocator($paths, $this->generator, $this->warmer, $this->assets);

        self::assertSame(0, $relocator->copyToNewLocation($this->makeMovingAsset(), false));
    }

    #[DataProvider('saveWithoutRelocationProvider')]
    public function testIgnoresSavesThatDoNotRelocateAnExistingImage(
        ?int $id,
        string $kind,
        ?string $newLocation,
        bool $isNew
    ): void {
        $this->filesOnDisk  = ['old/640/hero.jpg'];
        $asset              = $this->makeMovingAsset();
        $asset->id          = $id;
        $asset->kind        = $kind;
        $asset->newLocation = $newLocation;

        $this->fs->expects($this->never())->method('copyFile');

        self::assertSame(0, $this->relocator->copyToNewLocation($asset, $isNew));
    }

    /**
     * @return array<string, array{?int, string, ?string, bool}>
     */
    public static function saveWithoutRelocationProvider(): array
    {
        $location = '{folder:' . self::NEW_FOLDER_ID . '}hero.jpg';

        return [
            'new upload'             => [null, Asset::KIND_IMAGE, $location, true],
            'saved asset with no id' => [null, Asset::KIND_IMAGE, $location, false],
            'moved PDF'              => [7, Asset::KIND_PDF, $location, false],
            'no pending location'    => [7, Asset::KIND_IMAGE, null, false],
        ];
    }

    public function testIgnoresAMoveIntoAFolderThatCannotBeFound(): void
    {
        $this->filesOnDisk  = ['old/640/hero.jpg'];
        $asset              = $this->makeMovingAsset();
        $asset->newLocation = '{folder:999}hero.jpg';

        $this->fs->expects($this->never())->method('copyFile');

        self::assertSame(0, $this->relocator->copyToNewLocation($asset, false));
    }

    public function testIgnoresAMoveIntoAnotherVolume(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg'];
        $assets            = $this->createMock(AssetsService::class);
        $assets->method('getFolderById')->willReturn($this->makeFolder('elsewhere/', self::VOLUME_ID + 1));

        $this->fs->expects($this->never())->method('copyFile');

        $relocator = new VariantRelocator($this->paths, $this->generator, $this->warmer, $assets);

        self::assertSame(0, $relocator->copyToNewLocation($this->makeMovingAsset(), false));
    }

    public function testIgnoresAVolumeWithNoAssignedMatrices(): void
    {
        $warmer = $this->createMock(Warmer::class);
        $warmer->method('matrixHandlesForVolume')->willReturn([]);
        $this->filesOnDisk = ['old/640/hero.jpg'];

        $this->fs->expects($this->never())->method('copyFile');

        $relocator = new VariantRelocator($this->paths, $this->generator, $warmer, $this->assets);
        $asset     = $this->makeMovingAsset();

        self::assertSame(0, $relocator->copyToNewLocation($asset, false));
        self::assertSame([], $relocator->finishRelocation($asset));
    }

    public function testFinishingDeletesTheOldCopiesAndReportsTheMatricesWithoutGenerating(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg', 'old/1280/hero.jpg'];
        $asset             = $this->makeMovingAsset();
        $this->relocator->copyToNewLocation($asset, false);

        $deleted = [];
        $this->fs->method('deleteFile')->willReturnCallback(static function (string $uri) use (&$deleted): void {
            $deleted[] = $uri;
        });
        $this->generator->expects($this->never())->method('generate');

        self::assertSame(['product'], $this->relocator->finishRelocation($asset));
        self::assertSame(['old/640/hero.jpg', 'old/1280/hero.jpg'], $deleted);
    }

    public function testFinishingSkipsOldCopiesCraftHasAlreadyDeleted(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg', 'old/1280/hero.jpg'];
        $asset             = $this->makeMovingAsset();
        $this->relocator->copyToNewLocation($asset, false);
        $this->filesOnDisk = ['old/1280/hero.jpg'];

        $this->fs->expects($this->once())->method('deleteFile')->with('old/1280/hero.jpg');

        $this->relocator->finishRelocation($asset);
    }

    public function testFinishingIsANoOpWhenNoRelocationIsPending(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg'];

        $this->fs->expects($this->never())->method('deleteFile');
        self::assertSame([], $this->relocator->finishRelocation($this->makeMovingAsset()));
    }

    public function testFinishingIsANoOpForAnUnsavedAsset(): void
    {
        $asset     = $this->makeMovingAsset();
        $asset->id = null;

        self::assertSame([], $this->relocator->finishRelocation($asset));
    }

    public function testAPendingRelocationIsOnlyFinishedOnce(): void
    {
        $this->filesOnDisk = ['old/640/hero.jpg'];
        $asset             = $this->makeMovingAsset();
        $this->relocator->copyToNewLocation($asset, false);

        self::assertSame(['product'], $this->relocator->finishRelocation($asset));
        self::assertSame([], $this->relocator->finishRelocation($asset));
    }

    private function makeMovingAsset(string $newFilename = 'hero.jpg'): Asset&MockObject
    {
        $volume = $this->createMock(Volume::class);
        $volume->method('getTransformFs')->willReturn($this->fs);

        $asset              = $this->createMock(Asset::class);
        $asset->id          = 7;
        $asset->kind        = Asset::KIND_IMAGE;
        $asset->newLocation = '{folder:' . self::NEW_FOLDER_ID . '}' . $newFilename;
        $asset->method('getVolume')->willReturn($volume);
        $asset->method('getVolumeId')->willReturn(self::VOLUME_ID);

        return $asset;
    }

    private function makeFolder(string $path, int $volumeId): VolumeFolder
    {
        $folder           = $this->createMock(VolumeFolder::class);
        $folder->path     = $path;
        $folder->volumeId = $volumeId;

        return $folder;
    }
}
