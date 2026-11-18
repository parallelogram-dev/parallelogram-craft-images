<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\config;

use parallelogram\images\config\AssignmentConfig;
use parallelogram\images\config\ConfigValues;
use parallelogram\images\sizing\FieldAssignment;
use parallelogram\images\sizing\FieldAssignments;
use parallelogram\images\sizing\InvalidSizeSetException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AssignmentConfig::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(FieldAssignment::class)]
#[UsesClass(FieldAssignments::class)]
#[UsesClass(InvalidSizeSetException::class)]
#[Group('unit')]
final class AssignmentConfigTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidConfigProvider(): array
    {
        return [
            'not a list'          => ['card', 'Field assignments must be a list.'],
            'entry not an array'  => [['card'], 'Each field assignment needs a field and its size sets.'],
            'size sets not names' => [[['fieldUid' => 'f', 'sizeSets' => [1]]], 'lists size sets that aren\'t names'],
            'no field'            => [[['sizeSets' => ['card']]], 'A field assignment needs a field.'],
        ];
    }

    #[Test]
    public function readsCheckboxInputSkippingEmptyFields(): void
    {
        $input = [
            '__submitted' => '1',
            'field-1'     => ['card', '', 'tile'],
            'field-2'     => '',
            7             => ['card'],
        ];

        static::assertEquals(
            new FieldAssignments([new FieldAssignment('field-1', ['card', 'tile'])]),
            AssignmentConfig::fromInput($input),
        );
    }

    #[Test]
    public function readsNothingFromInputThatIsNotAnArray(): void
    {
        static::assertEquals(new FieldAssignments(), AssignmentConfig::fromInput('none'));
    }

    #[DataProvider('invalidConfigProvider')]
    #[Test]
    public function rejectsInvalidConfig(mixed $config, string $message): void
    {
        $this->expectException(InvalidSizeSetException::class);
        $this->expectExceptionMessage($message);

        AssignmentConfig::fromArray($config);
    }

    #[Test]
    public function roundTripsStoredAssignments(): void
    {
        $stored = [
            ['fieldUid' => 'field-1', 'layoutUid' => null, 'sizeSets' => ['card']],
            ['fieldUid' => 'field-1', 'layoutUid' => 'layout-a', 'sizeSets' => ['hero']],
        ];

        static::assertSame($stored, AssignmentConfig::toArray(AssignmentConfig::fromArray($stored)));
    }

    #[Test]
    public function treatsABlankLayoutAsEveryLayout(): void
    {
        $assignments = AssignmentConfig::fromArray([[
            'fieldUid'  => 'field-1',
            'layoutUid' => '',
            'sizeSets'  => ['card'],
        ]]);

        static::assertSame(['card'], $assignments->sizeSetsFor('field-1', layoutUid: 'any'));
    }
}
