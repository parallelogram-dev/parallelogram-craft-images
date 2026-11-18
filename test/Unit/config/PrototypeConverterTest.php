<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\config;

use parallelogram\images\config\ConfigValues;
use parallelogram\images\config\PrototypeConversion;
use parallelogram\images\config\PrototypeConverter;
use parallelogram\images\config\PrototypeMatrix;
use parallelogram\images\config\PrototypeVariant;
use parallelogram\images\sizing\AspectRatio;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PrototypeConverter::class)]
#[CoversClass(PrototypeConversion::class)]
#[CoversClass(PrototypeMatrix::class)]
#[CoversClass(PrototypeVariant::class)]
#[UsesClass(AspectRatio::class)]
#[UsesClass(ConfigValues::class)]
#[Group('unit')]
final class PrototypeConverterTest extends TestCase
{
    #[Test]
    public function convertsAWidthOnlyMatrix(): void
    {
        $conversion = $this->convert([
            'full' => [
                'sizes'    => '100vw',
                'formats'  => ['avif', 'webp', 'jpg'],
                'variants' => [['width' => 640], ['width' => 1280]],
            ],
        ]);

        static::assertSame(
            [
                'full' => [
                    'widths'  => [640, 1280],
                    'sizes'   => '100vw',
                    'ratio'   => null,
                    'mode'    => '',
                    'formats' => ['avif', 'webp', 'jpg'],
                    'quality' => [],
                ],
            ],
            $conversion->sizeSets,
        );
    }

    #[Test]
    public function dropsAssignmentsForMissingFieldsWithANote(): void
    {
        $conversion = $this->convert(['full' => ['variants' => [['width' => 640]]]], ['oldField' => ['full']]);

        static::assertSame(
            [[], ['The field “oldField” no longer exists, so its assignment was dropped.']],
            [$conversion->fieldAssignments, $conversion->notes],
        );
    }

    #[Test]
    public function dropsAssignmentsKeyedByNumber(): void
    {
        $conversion = $this->convert(['full' => ['variants' => [['width' => 640]]]], [['full']]);

        static::assertSame(
            [[], ['The field “0” no longer exists, so its assignment was dropped.']],
            [$conversion->fieldAssignments, $conversion->notes],
        );
    }

    #[Test]
    public function keepsEachImagesProportionsWhenShapesDiffer(): void
    {
        $conversion = $this->convert([
            'card' => ['variants' => [['width' => 400, 'height' => 300], ['width' => 400, 'height' => 400]]],
        ]);

        static::assertSame(
            [
                [
                    'widths'  => [400, 400],
                    'sizes'   => '',
                    'ratio'   => null,
                    'mode'    => '',
                    'formats' => [],
                    'quality' => [],
                ],
                ['“card” mixed variants of different shapes. It now keeps each image\'s own proportions.'],
            ],
            [$conversion->sizeSets['card'] ?? [], $conversion->notes],
        );
    }

    #[Test]
    public function leavesOutHeightOnlyVariantsWithANote(): void
    {
        $conversion = $this->convert(['tall' => ['variants' => [['height' => 600], ['width' => 300]]]]);

        static::assertSame(
            [
                [300],
                ['“tall” had a height-only variant, which size sets can\'t express. It was left out.'],
            ],
            [$conversion->sizeSets['tall']['widths'] ?? [], $conversion->notes],
        );
    }

    #[Test]
    public function mapsAssignmentsToFieldUidsForConvertedSizeSets(): void
    {
        $conversion = $this->convert(
            ['full' => ['variants' => [['width' => 640]]]],
            ['image' => ['full', 'gone'], 'preview' => ['gone']],
        );

        static::assertSame(
            [['fieldUid' => 'uid-image', 'layoutUid' => null, 'sizeSets' => ['full']]],
            $conversion->fieldAssignments,
        );
    }

    #[Test]
    public function readsASharedShapeAsARatio(): void
    {
        $conversion = $this->convert([
            'card' => ['variants' => [['width' => 400, 'height' => 300], ['width' => 800, 'height' => 600]]],
        ]);

        static::assertSame('4:3', $conversion->sizeSets['card']['ratio'] ?? null);
    }

    #[Test]
    public function skipsMatricesWithoutWidths(): void
    {
        $conversion = $this->convert(['empty' => ['variants' => 'none'], 'junk' => 'not a matrix']);

        static::assertSame([[], ['“empty” had no widths, so it wasn\'t converted.']], [
            $conversion->sizeSets,
            $conversion->notes,
        ]);
    }

    #[Test]
    public function toleratesSettingsThatAreNotArrays(): void
    {
        $conversion = PrototypeConverter::convert(
            null,
            assignments: null,
            fieldUidForHandle: static fn(): null => null,
        );

        static::assertEquals(new PrototypeConversion([], [], []), $conversion);
    }

    #[Test]
    public function treatsNonNumericDimensionsAsMissing(): void
    {
        $conversion = $this->convert([
            'card'    => ['variants' => [['width' => '640', 'height' => '480'], ['width' => 320, 'height' => 'auto']]],
            'nothing' => ['sizes' => '100vw'],
        ]);

        static::assertSame(
            [
                [320],
                [
                    '“card” had a height-only variant, which size sets can\'t express. It was left out.',
                    '“nothing” had no widths, so it wasn\'t converted.',
                ],
            ],
            [$conversion->sizeSets['card']['widths'] ?? [], $conversion->notes],
        );
    }

    /**
     * @param array<array-key, mixed> $matrices
     * @param array<array-key, mixed> $assignments
     */
    private function convert(array $matrices, array $assignments = []): PrototypeConversion
    {
        return PrototypeConverter::convert(
            $matrices,
            $assignments,
            static fn(string $handle): ?string => 'oldField' === $handle ? null : "uid-{$handle}",
        );
    }
}
