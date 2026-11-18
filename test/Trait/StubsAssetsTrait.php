<?php

declare(strict_types=1);

namespace parallelogram\images\test\Trait;

use craft\elements\Asset;
use craft\models\Volume;
use DateTime;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidConfigException;

use function pathinfo;

use const PATHINFO_EXTENSION;

/**
 * Stub Craft assets, without a running Craft site. A null URL or volume UID
 * stands for one Craft can't work out, so asking for it throws.
 *
 * @internal
 *
 * @require-extends TestCase
 */
trait StubsAssetsTrait
{
    /**
     * @param array{uid?: string, filename?: string, width?: int, height?: int, alt?: string|null, url?: string|null, volumeUid?: string|null} $values
     */
    protected function stubAsset(array $values = []): Asset
    {
        $values += [
            'uid'       => SourceImageFactory::UID,
            'filename'  => 'harbour.jpg',
            'width'     => 2000,
            'height'    => 1500,
            'alt'       => 'Harbour at dawn',
            'url'       => '/uploads/harbour.jpg',
            'volumeUid' => 'volume-1',
        ];

        $asset               = $this->createStub(Asset::class);
        $asset->kind         = Asset::KIND_IMAGE;
        $asset->uid          = $values['uid'];
        $asset->alt          = $values['alt'];
        $asset->dateModified = new DateTime('@1762000000');
        $asset->method('getFilename')->willReturn($values['filename']);
        $asset->method('getExtension')->willReturn(pathinfo($values['filename'], PATHINFO_EXTENSION));
        $asset->method('getWidth')->willReturn($values['width']);
        $asset->method('getHeight')->willReturn($values['height']);
        $asset->method('getFocalPoint')->willReturn(null);

        $asset->method('getUrl')
            ->willReturnCallback(
                /**
                 * @throws InvalidConfigException
                 */
                static fn(): string => $values['url'] ?? throw new InvalidConfigException(
                    'No URL.',
                ),
            );
        $volumeUid = $values['volumeUid'];
        $volume    = null === $volumeUid ? null : $this->createStub(Volume::class);

        if (null !== $volume) {
            $volume->uid = $volumeUid;
        }

        $asset->method('getVolume')
            ->willReturnCallback(
                /**
                 * @throws InvalidConfigException
                 */
                static fn(): Volume => $volume ?? throw new InvalidConfigException(
                    'No volume.',
                ),
            );

        return $asset;
    }
}
