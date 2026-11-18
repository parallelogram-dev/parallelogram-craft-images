<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\models;

use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\EncodingConfig;
use parallelogram\images\config\FitConfig;
use parallelogram\images\config\SizeSetConfig;
use parallelogram\images\config\SizeSetTable;
use parallelogram\images\models\Settings;
use parallelogram\images\models\SettingsView;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\InvalidSizeSetException;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SettingsView::class)]
#[UsesClass(AssignmentConfig::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(EncodingConfig::class)]
#[UsesClass(FieldAssignment::class)]
#[UsesClass(FieldAssignments::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FitConfig::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(Settings::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SizeSetConfig::class)]
#[UsesClass(SizeSetTable::class)]
#[Group('unit')]
final class SettingsViewTest extends TestCase
{
    private const array CARD = ['card' => ['widths' => [480], 'sizes' => '100vw']];

    #[Test]
    public function listsAssignedHandlesForAField(): void
    {
        $view = new SettingsView(new Settings([
            'sizeSets'         => self::CARD,
            'fieldAssignments' => [['fieldUid' => 'field-1', 'sizeSets' => ['card']]],
        ]));

        static::assertSame([['card'], []], [$view->handlesAssignedTo('field-1'), $view->handlesAssignedTo('field-2')]);
    }

    #[Test]
    public function listsSizeSetHandles(): void
    {
        static::assertSame(['card'], (new SettingsView(new Settings(['sizeSets' => self::CARD])))->sizeSetHandles());
    }

    #[Test]
    public function showsPostedRowsAgainAfterAnError(): void
    {
        $rows = [['handle' => 'card', 'widths' => 'oops']];

        static::assertSame($rows, (new SettingsView(new Settings(['sizeSetRows' => $rows])))->sizeSetRows());
    }

    #[Test]
    public function showsStoredSizeSetsAsRows(): void
    {
        $rows = (new SettingsView(new Settings(['sizeSets' => self::CARD])))->sizeSetRows();

        static::assertSame('card', $rows[0]['handle'] ?? null);
    }

    #[Test]
    public function toleratesInvalidStoredSettings(): void
    {
        $view = new SettingsView(new Settings(['sizeSets' => ['card' => ['widths' => []]]]));

        static::assertSame([[], [], []], [
            $view->sizeSetRows(),
            $view->sizeSetHandles(),
            $view->handlesAssignedTo('field-1'),
        ]);
    }
}
