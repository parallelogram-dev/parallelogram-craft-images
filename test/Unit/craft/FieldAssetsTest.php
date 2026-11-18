<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\ElementCollection;
use craft\errors\InvalidFieldException;
use parallelogram\images\craft\FieldAssets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FieldAssets::class)]
#[Group('unit')]
final class FieldAssetsTest extends TestCase
{
    #[Test]
    public function findsNothingInAFieldThatIsntThere(): void
    {
        $element = $this->createStub(ElementInterface::class);
        $element->method('getFieldValue')->willThrowException(new InvalidFieldException('gallery'));

        static::assertSame([], FieldAssets::idsIn($element, handle: 'gallery'));
    }

    #[Test]
    public function findsNothingInOtherValues(): void
    {
        static::assertSame([], FieldAssets::idsIn($this->elementWith('not assets'), handle: 'gallery'));
    }

    #[Test]
    public function readsTheIdsOfImagesFromAnEagerLoadedCollection(): void
    {
        static::assertSame(
            [7, 9],
            FieldAssets::idsIn($this->elementWith(new ElementCollection([
                $this->asset(7, Asset::KIND_IMAGE),
                $this->asset(8, Asset::KIND_PDF),
                $this->asset(null, Asset::KIND_IMAGE),
                $this->asset(9, Asset::KIND_IMAGE),
            ])), handle: 'gallery'),
        );
    }

    #[Test]
    public function readsTheIdsOfImagesFromAQuery(): void
    {
        $query = $this->createStub(AssetQuery::class);
        $query->method('kind')->willReturnSelf();
        $query->method('ids')->willReturn(['4', 5]);

        static::assertSame([4, 5], FieldAssets::idsIn($this->elementWith($query), handle: 'gallery'));
    }

    private function asset(?int $id, string $kind): Asset
    {
        $asset       = $this->createStub(Asset::class);
        $asset->id   = $id;
        $asset->kind = $kind;

        return $asset;
    }

    private function elementWith(mixed $value): ElementInterface
    {
        $element = $this->createStub(ElementInterface::class);
        $element->method('getFieldValue')->willReturn($value);

        return $element;
    }
}
