<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use parallelogram\images\craft\BatchResult;
use parallelogram\images\craft\CommandOutcome;
use parallelogram\images\warming\WarmResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BatchResult::class)]
#[CoversClass(CommandOutcome::class)]
#[UsesClass(WarmResult::class)]
#[Group('unit')]
final class BatchResultTest extends TestCase
{
    #[Test]
    public function addsRunsTogether(): void
    {
        $total = (new BatchResult(
            images: 2,
            skipped: 1,
            files: new WarmResult(
                made: 3,
                present: 4,
                failed: 1,
            ),
            missing: 5,
        ))
            ->plus(new BatchResult(
                images: 1,
                skipped: 0,
                files: new WarmResult(
                    made: 1,
                    present: 1,
                    failed: 0,
                ),
                missing: 2,
            ));

        static::assertEquals(
            new BatchResult(
                images: 3,
                skipped: 1,
                files: new WarmResult(
                    made: 4,
                    present: 5,
                    failed: 1,
                ),
                missing: 7,
            ),
            $total,
        );
    }

    #[Test]
    public function carriesWhatACommandShouldPrint(): void
    {
        $outcome = new CommandOutcome(1, ['Something is off.']);

        static::assertSame([1, ['Something is off.']], [$outcome->exit, $outcome->lines]);
    }

    #[Test]
    public function startsEmpty(): void
    {
        static::assertSame(
            [0, 0, 0, 0, 0, 0],
            [
                (new BatchResult())->images,
                (new BatchResult())->skipped,
                (new BatchResult())->files->made,
                (new BatchResult())->files->present,
                (new BatchResult())->files->failed,
                (new BatchResult())->missing,
            ],
        );
    }

    #[Test]
    public function summarisesInOneLine(): void
    {
        $result = new BatchResult(
            images: 12,
            skipped: 2,
            files: new WarmResult(
                made: 30,
                present: 6,
                failed: 1,
            ),
            missing: 4,
        );

        static::assertSame(
            'Warmed 12 images: 30 made, 6 already there, 1 failed, 4 missing, 2 skipped.',
            $result->summary(verb: 'Warmed'),
        );
    }
}
