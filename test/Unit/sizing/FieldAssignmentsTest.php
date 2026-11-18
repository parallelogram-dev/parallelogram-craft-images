<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\sizing;

use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
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
#[CoversClass(FieldAssignments::class)]
#[CoversClass(FieldAssignment::class)]
#[CoversClass(InvalidSizeSetException::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(Format::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[Group('unit')]
final class FieldAssignmentsTest extends TestCase
{
    #[Test]
    public function combinesEverywhereAndLayoutAssignmentsWithoutRepeats(): void
    {
        $assignments = new FieldAssignments([
            new FieldAssignment('field-1', ['card', 'tile']),
            new FieldAssignment('field-1', ['tile', 'hero'], layoutUid: 'layout-a'),
            new FieldAssignment('field-1', ['swatch'], layoutUid: 'layout-b'),
            new FieldAssignment('field-2', ['full']),
        ]);

        static::assertSame(['card', 'tile', 'hero'], $assignments->sizeSetsFor('field-1', layoutUid: 'layout-a'));
    }

    #[Test]
    public function dropsDeletedSizeSetsAndEmptiedAssignments(): void
    {
        $assignments = new FieldAssignments([
            new FieldAssignment('field-1', ['card', 'gone']),
            new FieldAssignment('field-2', ['gone']),
        ]);

        $pruned = $assignments->prunedTo(new SizeSetCatalogue([SizeSetFactory::make(handle: 'card')]));

        static::assertEquals([new FieldAssignment('field-1', ['card'])], $pruned->assignments);
    }

    #[Test]
    public function ignoresLayoutAssignmentsWhenNoLayoutIsGiven(): void
    {
        $assignments = new FieldAssignments([new FieldAssignment('field-1', ['hero'], layoutUid: 'layout-a')]);

        static::assertSame([], $assignments->sizeSetsFor('field-1', layoutUid: null));
    }

    #[Test]
    public function refusesAnAssignmentWithoutAField(): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('A field assignment needs a field.');

        new FieldAssignment('', ['card']);
    }

    #[Test]
    public function refusesAnAssignmentWithoutSizeSets(): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage('A field assignment needs at least one size set.');

        new FieldAssignment('field-1', []);
    }

    #[Test]
    public function removesRepeatedHandles(): void
    {
        static::assertSame(['card', 'tile'], (new FieldAssignment('field-1', ['card', 'tile', 'card']))->sizeSets);
    }
}
