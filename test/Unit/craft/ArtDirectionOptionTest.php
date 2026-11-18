<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use parallelogram\images\craft\ArtDirectionOption;
use parallelogram\images\picture\InvalidPictureException;
use parallelogram\images\picture\OptionReader;
use parallelogram\images\test\Trait\StubsAssetsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ArtDirectionOption::class)]
#[UsesClass(OptionReader::class)]
#[Group('unit')]
final class ArtDirectionOptionTest extends TestCase
{
    use StubsAssetsTrait;

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidSourcesProvider(): array
    {
        return [
            'not a list'       => ['(max-width: 600px)', 'The sources option must be a list'],
            'entry not a hash' => [
                ['(max-width: 600px)'],
                'Each art-directed source must be a {media, asset, sizeSet, blank} hash.',
            ],
            'unknown key' => [[['media' => 'x', 'matrix' => 'card']], 'Unknown art-directed source option: matrix.'],
            'no media'    => [[['sizeSet' => 'square']], 'needs a media query'],
            'empty media' => [[['media' => '']], 'needs a media query'],
            'asset not asset'  => [[['media' => 'x', 'asset' => 42]], 'asset must be an asset'],
            'blank not a flag' => [[['media' => 'x', 'blank' => 'yes']], 'The blank option must be true or false.'],
            'blank with a size set' => [
                [['media' => 'x', 'blank' => true, 'sizeSet' => 'square']],
                'A blank art-directed source shows no image',
            ],
            'blank with an asset'   => [
                [['media' => 'x', 'blank' => true, 'asset' => 'asset']],
                'asset must be an asset',
            ],
        ];
    }

    #[Test]
    public function readsBlankSources(): void
    {
        static::assertEquals(
            [new ArtDirectionOption('(max-width: 599px)', blank: true)],
            ArtDirectionOption::listFrom(['sources' => [['media' => '(max-width: 599px)', 'blank' => true]]]),
        );
    }

    #[Test]
    public function readsEachSource(): void
    {
        $asset = $this->stubAsset();

        static::assertEquals(
            [new ArtDirectionOption('(max-width: 600px)', $asset, 'square'), new ArtDirectionOption('print')],
            ArtDirectionOption::listFrom([
                'sources' => [
                    ['media' => '(max-width: 600px)', 'asset' => $asset, 'sizeSet' => 'square'],
                    ['media' => 'print', 'asset' => null],
                ],
            ]),
        );
    }

    #[Test]
    public function readsNoSourcesWhenTheOptionIsLeftOut(): void
    {
        static::assertSame([], ArtDirectionOption::listFrom([]));
    }

    #[DataProvider('invalidSourcesProvider')]
    #[Test]
    public function refusesSourcesItCantUse(mixed $sources, string $message): void
    {
        $this->expectException(InvalidPictureException::class);
        $this->expectExceptionMessage($message);

        ArtDirectionOption::listFrom(['sources' => $sources]);
    }
}
