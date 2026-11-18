<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\AlternateImage;
use parallelogram\images\sizing\AlternateImages;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AlternateImages::class)]
#[CoversClass(AlternateImage::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(Format::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[Group('unit')]
final class AlternateImagesTest extends TestCase
{
    #[Test]
    public function findsASizeSetsAlternate(): void
    {
        $alternate  = new AlternateImage('field-1', 'print');
        $alternates = new AlternateImages(['wide' => $alternate]);

        static::assertSame([$alternate, null], [$alternates->for('wide'), $alternates->for('card')]);
    }

    #[Test]
    public function reportsSizeSetsThatDontExist(): void
    {
        $alternates = new AlternateImages([
            'wide' => new AlternateImage('field-1', 'print', 'round'),
            'gone' => new AlternateImage('field-1', 'print'),
            'card' => new AlternateImage('field-1', 'print', 'card'),
        ]);

        static::assertSame(
            [
                '“wide” shows its alternate image as “round”, which isn\'t a size set.',
                'There\'s an alternate image for “gone”, which isn\'t a size set.',
            ],
            $alternates->problemsWith(new SizeSetCatalogue([
                SizeSetFactory::make('wide'),
                SizeSetFactory::make('card'),
            ])),
        );
    }
}
