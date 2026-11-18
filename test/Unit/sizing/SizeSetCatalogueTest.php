<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\sizing\UnknownSizeSetException;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SizeSetCatalogue::class)]
#[CoversClass(UnknownSizeSetException::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[Group('unit')]
final class SizeSetCatalogueTest extends TestCase
{
    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function findsASizeSetByHandle(): void
    {
        $card = SizeSetFactory::make(handle: 'card');

        static::assertSame($card, (new SizeSetCatalogue([SizeSetFactory::make(handle: 'hero'), $card]))->get('card'));
    }

    #[Test]
    public function listsHandlesAndSizeSetsInOrder(): void
    {
        $hero      = SizeSetFactory::make(handle: 'hero');
        $card      = SizeSetFactory::make(handle: 'card');
        $catalogue = new SizeSetCatalogue([$hero, $card]);

        static::assertSame([['hero', 'card'], [$hero, $card]], [$catalogue->handles(), $catalogue->all()]);
    }

    /**
     * @throws UnknownSizeSetException
     */
    #[Test]
    public function refusesAnUnknownHandle(): void
    {
        $this->expectException(UnknownSizeSetException::class);
        $this->expectExceptionMessage('There is no size set called “tile”.');

        (new SizeSetCatalogue([]))->get('tile');
    }

    #[Test]
    public function refusesRepeatedHandles(): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('Two size sets use the handle “card”.');

        new SizeSetCatalogue([SizeSetFactory::make(), SizeSetFactory::make()]);
    }

    #[Test]
    public function reportsWhetherItHasAHandle(): void
    {
        $catalogue = new SizeSetCatalogue([SizeSetFactory::make(handle: 'card')]);

        static::assertSame([true, false], [$catalogue->has('card'), $catalogue->has('tile')]);
    }
}
