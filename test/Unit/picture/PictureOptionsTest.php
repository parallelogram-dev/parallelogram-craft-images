<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\picture;

use parallelogram\images\picture\ElementAttributes;
use parallelogram\images\picture\InvalidPictureException;
use parallelogram\images\picture\Loading;
use parallelogram\images\picture\OptionReader;
use parallelogram\images\picture\PictureOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PictureOptions::class)]
#[CoversClass(OptionReader::class)]
#[CoversClass(ElementAttributes::class)]
#[Group('unit')]
final class PictureOptionsTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidOptionsProvider(): array
    {
        return [
            'unknown option'                => [['lazy' => true], 'Unknown picture option: lazy.'],
            'text that isn\'t'              => [['class' => ['hero']], 'The class option must be text.'],
            'flag that isn\'t'              => [['lazysrc' => 'yes'], 'The lazysrc option must be true or false.'],
            'unchecked flag'                => [
                ['priority' => true, 'lazysrc' => 1],
                'The lazysrc option must be true or false.',
            ],
            'picture attributes not a hash' => [
                ['pictureAttributes' => 'id=top'],
                'The pictureAttributes option must be a hash',
            ],
            'attributes not a hash' => [['attributes' => 'id=hero'], 'must be a hash of attribute names and values.'],
            'attribute value'       => [
                ['attributes' => ['data-x' => ['a']]],
                'must be text, numbers, true, false or null.',
            ],
            'numbered attribute'    => [['attributes' => ['hero']], '“0” isn\'t an attribute name.'],
            'unsafe name'           => [['attributes' => ['onload x' => 'a']], '“onload x” isn\'t an attribute name.'],
        ];
    }

    #[Test]
    public function defaultsToLazyLoadingWithTheAssetsAltText(): void
    {
        static::assertEquals(
            new PictureOptions('Harbour at dawn'),
            PictureOptions::fromArray([], defaultAlt: 'Harbour at dawn'),
        );
    }

    #[Test]
    public function followsTheSitesLazysrcDefaultUnlessToldOtherwise(): void
    {
        static::assertSame(
            [Loading::Lazysrc, Loading::Lazy],
            [
                PictureOptions::fromArray([], defaultAlt: '', default: Loading::Lazysrc)->loading,
                PictureOptions::fromArray(['lazysrc' => false], defaultAlt: '', default: Loading::Lazysrc)->loading,
            ],
        );
    }

    #[Test]
    public function letsPriorityWinOverLazysrc(): void
    {
        static::assertSame(
            Loading::Priority,
            PictureOptions::fromArray(['lazysrc' => true, 'priority' => true], defaultAlt: '')->loading,
        );
    }

    #[Test]
    public function readsEveryOption(): void
    {
        static::assertEquals(
            new PictureOptions(
                'A boat',
                '50vw',
                Loading::Lazysrc,
                new ElementAttributes('hero', ['id' => 'top', 'data-n' => 2, 'hidden' => null]),
                new ElementAttributes('frame', ['data-reveal' => true]),
            ),
            PictureOptions::fromArray(
                [
                    'alt'               => 'A boat',
                    'class'             => 'hero',
                    'sizes'             => '50vw',
                    'lazysrc'           => true,
                    'priority'          => false,
                    'attributes'        => ['id' => 'top', 'data-n' => 2, 'hidden' => null],
                    'pictureClass'      => 'frame',
                    'pictureAttributes' => ['data-reveal' => true],
                    'sources'           => [],
                ],
                defaultAlt: 'Harbour',
            ),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidOptionsProvider')]
    #[Test]
    public function refusesOptionsItCantUse(array $options, string $message): void
    {
        $this->expectException(InvalidPictureException::class);
        $this->expectExceptionMessage($message);

        PictureOptions::fromArray($options, defaultAlt: '');
    }

    #[Test]
    public function usesTheAssetsAltTextWhenAltIsNull(): void
    {
        static::assertSame('Harbour', PictureOptions::fromArray(['alt' => null], defaultAlt: 'Harbour')->alt);
    }
}
