<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\elements\Asset;
use DateTime;
use parallelogram\images\craft\AssetSource;
use parallelogram\images\craft\CraftSourceLookup;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\SourceImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidConfigException;

use function pathinfo;

use const PATHINFO_EXTENSION;

/**
 * Assets are stubbed: a real one needs a running Craft application.
 *
 * @internal
 */
#[CoversClass(AssetSource::class)]
#[CoversClass(CraftSourceLookup::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(SourceImage::class)]
#[Group('unit')]
final class AssetSourceTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function focalPointProvider(): array
    {
        return [
            'set'          => [['x' => 0.25, 'y' => '0.75'], '0.2500,0.7500'],
            'not an array' => ['50% 50%', '0.5000,0.5000'],
            'missing y'    => [['x' => 0.25], '0.5000,0.5000'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function untouchedFilenameProvider(): array
    {
        return ['svg' => ['logo.svg'], 'gif' => ['spinner.GIF']];
    }

    /**
     * @return array<string, array{string, string|null, int|null, int|null, string}>
     */
    public static function unusableAssetProvider(): array
    {
        $uid = SourceImageFactory::UID;

        return [
            'not an image'       => [Asset::KIND_PDF, $uid, 100, 100, 'file.pdf'],
            'no uid'             => [Asset::KIND_IMAGE, null, 100, 100, 'harbour.jpg'],
            'no dimensions'      => [Asset::KIND_IMAGE, $uid, null, null, 'harbour.jpg'],
            'unusable file name' => [Asset::KIND_IMAGE, $uid, 100, 100, '...'],
        ];
    }

    #[DataProvider('untouchedFilenameProvider')]
    #[Test]
    public function leavesSvgsAndGifsAsUploaded(string $filename): void
    {
        static::assertNull(AssetSource::from($this->asset(['filename' => $filename])));
    }

    #[Test]
    public function looksUpAssetsByUid(): void
    {
        $asset  = $this->asset();
        $lookup = new CraftSourceLookup(static fn(string $uid): ?Asset => SourceImageFactory::UID === $uid
            ? $asset
            : null);

        static::assertSame(
            [SourceImageFactory::UID, null],
            [$lookup->find(SourceImageFactory::UID)?->uid, $lookup->find('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')],
        );
    }

    #[DataProvider('focalPointProvider')]
    #[Test]
    public function readsTheFocalPoint(mixed $focalPoint, string $expected): void
    {
        $source = AssetSource::from($this->asset(['focalPoint' => $focalPoint]));

        static::assertSame($expected, $source?->focalPoint->toString());
    }

    #[Test]
    public function readsTheSourceFacts(): void
    {
        $source = AssetSource::from($this->asset());

        static::assertEquals(
            new SourceImage(SourceImageFactory::UID, 'harbour.jpg', new Dimensions(2000, 1500), 1_762_000_000),
            $source,
        );
    }

    #[DataProvider('unusableAssetProvider')]
    #[Test]
    public function skipsAssetsThatCannotBeResized(
        string $kind,
        ?string $uid,
        ?int $width,
        ?int $height,
        string $filename,
    ): void {
        static::assertNull(AssetSource::from($this->asset([
            'kind'     => $kind,
            'uid'      => $uid,
            'width'    => $width,
            'height'   => $height,
            'filename' => $filename,
        ])));
    }

    #[Test]
    public function skipsAssetsWhoseDimensionsCannotBeRead(): void
    {
        $asset       = $this->createStub(Asset::class);
        $asset->kind = Asset::KIND_IMAGE;
        $asset->uid  = SourceImageFactory::UID;
        $asset->method('getFilename')->willReturn('harbour.jpg');
        $asset->method('getWidth')->willThrowException(new InvalidConfigException('No transformer.'));

        static::assertNull(AssetSource::from($asset));
    }

    /**
     * @param array{kind?: string, uid?: string|null, width?: int|null, height?: int|null, filename?: string, focalPoint?: mixed} $values
     */
    private function asset(array $values = []): Asset
    {
        $values += [
            'kind'       => Asset::KIND_IMAGE,
            'uid'        => SourceImageFactory::UID,
            'width'      => 2000,
            'height'     => 1500,
            'filename'   => 'harbour.jpg',
            'focalPoint' => null,
        ];

        $asset               = $this->createStub(Asset::class);
        $asset->kind         = $values['kind'];
        $asset->uid          = $values['uid'];
        $asset->dateModified = new DateTime('@1762000000');
        $asset->method('getFilename')->willReturn($values['filename']);
        $asset->method('getExtension')->willReturn(pathinfo($values['filename'], PATHINFO_EXTENSION));
        $asset->method('getWidth')->willReturn($values['width']);
        $asset->method('getHeight')->willReturn($values['height']);
        $asset->method('getFocalPoint')->willReturn($values['focalPoint']);

        return $asset;
    }
}
