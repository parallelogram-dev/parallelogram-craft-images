<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\elements\Asset;
use craft\errors\FsException;
use parallelogram\images\craft\CraftSourceFile;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\SourceImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CraftSourceFile::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(GenerationFailedException::class)]
#[UsesClass(SourceImage::class)]
#[Group('unit')]
final class CraftSourceFileTest extends TestCase
{
    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function readsTheAssetsFile(): void
    {
        $asset = $this->createStub(Asset::class);
        $asset->method('getContents')->willReturn('original bytes');

        static::assertSame(
            'original bytes',
            (new CraftSourceFile(static fn(): Asset => $asset))->contents(SourceImageFactory::make()),
        );
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function reportsAMissingAsset(): void
    {
        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('no longer exists');

        (new CraftSourceFile(static fn(): null => null))->contents(SourceImageFactory::make());
    }

    /**
     * @throws GenerationFailedException
     */
    #[Test]
    public function reportsAnUnreadableFile(): void
    {
        $asset = $this->createStub(Asset::class);
        $asset->method('getContents')->willThrowException(new FsException('Gone.'));

        $this->expectException(GenerationFailedException::class);
        $this->expectExceptionMessage('Couldn\'t read the original file');

        (new CraftSourceFile(static fn(): Asset => $asset))->contents(SourceImageFactory::make());
    }
}
