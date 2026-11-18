<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\ElementCollection;
use parallelogram\images\craft\RelatedAsset;
use parallelogram\images\test\Trait\StubsAssetsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RelatedAsset::class)]
#[Group('unit')]
final class RelatedAssetTest extends TestCase
{
    use StubsAssetsTrait;

    private const string FIELD = '60f35b38-630a-49b5-9709-8c5355aaed13';

    #[Test]
    public function findsNothingInAFieldWithoutAHandle(): void
    {
        static::assertNull(RelatedAsset::in($this->stubAsset(['layoutFields' => [self::FIELD => null]]), self::FIELD));
    }

    #[Test]
    public function findsNothingInAnEmptyOrUnexpectedValue(): void
    {
        static::assertSame(
            [null, null],
            [
                RelatedAsset::in($this->withValue(new ElementCollection()), self::FIELD),
                RelatedAsset::in($this->withValue('not an asset'), self::FIELD),
            ],
        );
    }

    #[Test]
    public function findsNothingOutsideTheLayout(): void
    {
        static::assertSame(
            [null, null],
            [
                RelatedAsset::in($this->stubAsset(), self::FIELD),
                RelatedAsset::in($this->stubAsset(['layoutFields' => ['other' => 'other']]), self::FIELD),
            ],
        );
    }

    #[Test]
    public function findsNothingWhenTheFieldCantBeRead(): void
    {
        static::assertNull(RelatedAsset::in(
            $this->stubAsset(['layoutFields' => [self::FIELD => 'mobileAltImage']]),
            self::FIELD,
        ));
    }

    #[Test]
    public function readsAnEagerLoadedValue(): void
    {
        $related = $this->createStub(Asset::class);

        static::assertSame($related, RelatedAsset::in(
            $this->withValue(new ElementCollection([$related])),
            self::FIELD,
        ));
    }

    #[Test]
    public function readsAQueryUsingTheLayoutsHandle(): void
    {
        $related = $this->createStub(Asset::class);
        $query   = $this->createStub(AssetQuery::class);
        $query->method('one')->willReturn($related);

        static::assertSame($related, RelatedAsset::in($this->withValue($query), self::FIELD));
    }

    private function withValue(mixed $value): Asset
    {
        return $this->stubAsset([
            'layoutFields' => [self::FIELD => 'phoneImage'],
            'fieldValues'  => ['phoneImage' => $value],
        ]);
    }
}
