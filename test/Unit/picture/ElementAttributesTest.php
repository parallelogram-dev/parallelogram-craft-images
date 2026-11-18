<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\picture;

use parallelogram\images\picture\ElementAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ElementAttributes::class)]
#[Group('unit')]
final class ElementAttributesTest extends TestCase
{
    #[Test]
    public function letsTheTemplatesAttributesReplaceTheDefaults(): void
    {
        $attributes = new ElementAttributes('frame', ['style' => 'height:0', 'data-x' => true]);

        static::assertSame(
            ['class' => 'frame', 'style' => 'height:0', 'data-x' => true],
            $attributes->all(['style' => 'background-color:red']),
        );
    }

    #[Test]
    public function writesTheDefaultsWhenTheTemplateGivesNone(): void
    {
        static::assertSame(
            ['class' => null, 'style' => 'background-color:red'],
            (new ElementAttributes())->all(['style' => 'background-color:red']),
        );
    }
}
