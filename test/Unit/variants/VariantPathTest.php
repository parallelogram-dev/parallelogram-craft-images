<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\variants;

use parallelogram\images\sizing\Format;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\InvalidVariantException;
use parallelogram\images\variants\ParsedVariantPath;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariantPath::class)]
#[CoversClass(ParsedVariantPath::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(InvalidVariantException::class)]
#[UsesClass(SourceImage::class)]
#[Group('unit')]
final class VariantPathTest extends TestCase
{
    private const string UID = SourceImageFactory::UID;

    private const string HASH = '0123456789abcdef';

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPrefixProvider(): array
    {
        return [
            'empty'         => [''],
            'only slashes'  => [' / '],
            'parent folder' => ['asset/../secret'],
            'dot segment'   => ['./asset'],
            'empty segment' => ['asset//variant'],
            'space'         => ['my assets'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedRequestProvider(): array
    {
        $uid  = self::UID;
        $hash = self::HASH;

        return [
            'other prefix'     => ["media/{$uid}/{$hash}/harbour.avif"],
            'prefix lookalike' => ["asset/variants/{$uid}/{$hash}/harbour.avif"],
            'short hash'       => ["asset/variant/{$uid}/0123/harbour.avif"],
            'uppercase hash'   => ["asset/variant/{$uid}/0123456789ABCDEF/harbour.avif"],
            'not a uid'        => ["asset/variant/42/{$hash}/harbour.avif"],
            'extra folder'     => ["asset/variant/{$uid}/{$hash}/x/harbour.avif"],
            'no extension'     => ["asset/variant/{$uid}/{$hash}/harbour"],
            'unknown format'   => ["asset/variant/{$uid}/{$hash}/harbour.gif"],
            'empty basename'   => ["asset/variant/{$uid}/{$hash}/.avif"],
        ];
    }

    #[Test]
    public function buildsAPathUnderTheTrimmedPrefix(): void
    {
        $path = new VariantPath(' /asset/variant/ ');

        static::assertSame(
            'asset/variant/' . self::UID . '/' . self::HASH . '/harbour-dawn.avif',
            $path->pathFor(SourceImageFactory::make(), Format::Avif, self::HASH),
        );
    }

    #[Test]
    public function givesEachAssetItsOwnFolder(): void
    {
        static::assertSame('asset/variant/' . self::UID, (new VariantPath('asset/variant'))->folderFor(self::UID));
    }

    #[Test]
    public function readsAWellFormedRequest(): void
    {
        $parsed = (new VariantPath('asset/variant'))->parse(
            '/asset/variant/' . self::UID . '/' . self::HASH . '/harbour.dawn.jpg',
        );

        static::assertEquals(new ParsedVariantPath(self::UID, self::HASH, 'harbour.dawn', Format::Jpg), $parsed);
    }

    #[DataProvider('invalidPrefixProvider')]
    #[Test]
    public function rejectsAnUnsafePrefix(string $prefix): void
    {
        $this->expectException(InvalidVariantException::class);

        new VariantPath($prefix);
    }

    #[DataProvider('rejectedRequestProvider')]
    #[Test]
    public function rejectsMalformedRequests(string $requested): void
    {
        static::assertNull((new VariantPath('asset/variant'))->parse($requested));
    }
}
