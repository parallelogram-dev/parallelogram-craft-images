<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\picture;

use parallelogram\images\picture\RemoteVariantLinks;
use parallelogram\images\picture\StoredVariantLinks;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\test\TestAsset\Factory\VariantFileFactory;
use parallelogram\images\test\TestAsset\Fake\InMemoryVariantStore;
use parallelogram\images\test\TestAsset\Fake\QueryStringUrlService;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(StoredVariantLinks::class)]
#[CoversClass(RemoteVariantLinks::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[Group('unit')]
final class VariantLinksTest extends TestCase
{
    #[Test]
    public function linksStraightToFilesServedFromTheSiteDomain(): void
    {
        $links = new StoredVariantLinks(new InMemoryVariantStore('/files'), 'https://www.example.test');

        static::assertSame('/files/480.jpg', $links->urlFor(VariantFileFactory::make(480)));
    }

    #[Test]
    public function linksToTheImageServiceForRemoteVolumes(): void
    {
        $links = new RemoteVariantLinks(
            new QueryStringUrlService(),
            SourceImageFactory::make(),
            'https://www.example.test/uploads/harbour.jpg',
        );

        static::assertSame(
            'https://www.example.test/uploads/harbour.jpg?asset=' . SourceImageFactory::UID . '&w=480&f=avif',
            $links->urlFor(VariantFileFactory::make(480, Format::Avif)),
        );
    }

    #[Test]
    public function linksToTheSiteRouteWhenFilesLiveElsewhere(): void
    {
        $links = new StoredVariantLinks(
            new InMemoryVariantStore('https://bucket.test', onSiteDomain: false),
            'https://www.example.test/',
        );

        static::assertSame('https://www.example.test/480.jpg', $links->urlFor(VariantFileFactory::make(480)));
    }

    #[Test]
    public function linksToTheSiteRouteWhenTheFilesystemHasNoUrl(): void
    {
        $links = new StoredVariantLinks(new InMemoryVariantStore(null), 'https://www.example.test');

        static::assertSame('https://www.example.test/480.jpg', $links->urlFor(VariantFileFactory::make(480)));
    }
}
