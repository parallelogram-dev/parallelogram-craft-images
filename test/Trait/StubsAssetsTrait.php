<?php

declare(strict_types=1);

namespace parallelogram\images\test\Trait;

use craft\elements\Asset;
use craft\errors\InvalidFieldException;
use craft\fields\Assets;
use craft\models\FieldLayout;
use craft\models\Volume;
use DateTime;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use PHPUnit\Framework\TestCase;
use yii\base\InvalidConfigException;

use function array_key_exists;
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
     * `layoutFields` maps field UIDs to their handles in the asset's field
     * layout, and `fieldValues` maps handles to values; reading any other
     * handle throws, as Craft does.
     *
     * @param array{uid?: string, filename?: string, width?: int, height?: int, alt?: string|null, url?: string|null, volumeUid?: string|null, layoutFields?: array<string, string|null>, fieldValues?: array<string, mixed>} $values
     */
    protected function stubAsset(array $values = []): Asset
    {
        $values += [
            'uid'          => SourceImageFactory::UID,
            'filename'     => 'harbour.jpg',
            'width'        => 2000,
            'height'       => 1500,
            'alt'          => 'Harbour at dawn',
            'url'          => '/uploads/harbour.jpg',
            'volumeUid'    => 'volume-1',
            'layoutFields' => [],
            'fieldValues'  => [],
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

        $fieldValues = $values['fieldValues'];
        $asset->method('getFieldLayout')->willReturn($this->stubLayout($values['layoutFields']));
        $asset->method('getFieldValue')
            ->willReturnCallback(
                /**
                 * @throws InvalidFieldException
                 */
                static fn(string $handle): mixed => array_key_exists($handle, $fieldValues)
                    ? $fieldValues[$handle]
                    : throw new InvalidFieldException($handle),
            );

        return $asset;
    }

    /**
     * @param array<string, string|null> $fields Handles keyed by field UID.
     */
    private function stubLayout(array $fields): ?FieldLayout
    {
        if ([] === $fields) {
            return null;
        }

        $custom = [];

        foreach ($fields as $uid => $handle) {
            $field         = $this->createStub(Assets::class);
            $field->uid    = $uid;
            $field->handle = $handle;
            $custom[]      = $field;
        }

        $layout = $this->createStub(FieldLayout::class);
        $layout->method('getCustomFields')->willReturn($custom);

        return $layout;
    }
}
