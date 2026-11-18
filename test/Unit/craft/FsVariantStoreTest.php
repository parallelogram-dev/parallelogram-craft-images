<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\base\FsInterface;
use craft\errors\FsException;
use parallelogram\images\craft\FsVariantStore;
use parallelogram\images\delivery\StorageFailedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FsVariantStore::class)]
#[UsesClass(StorageFailedException::class)]
#[Group('unit')]
final class FsVariantStoreTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function failingOperationProvider(): array
    {
        return [
            'exists' => ['fileExists'],
            'read'   => ['read'],
            'write'  => ['write'],
        ];
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function siteDomainProvider(): array
    {
        return [
            'root-relative'     => ['/media', true],
            'same host'         => ['https://www.example.test/media', true],
            'other host'        => ['https://cdn.example.test', false],
            'protocol-relative' => ['//cdn.example.test', false],
            'no urls'           => [null, false],
            'empty url'         => ['', false],
            'not a url'         => ['http:///', false],
        ];
    }

    #[Test]
    public function encodesEachPathSegmentInUrls(): void
    {
        $store = new FsVariantStore($this->fs('https://cdn.example.test/'), onSiteDomain: false);

        static::assertSame(
            'https://cdn.example.test/asset/variant/Harbour%20Dawn.avif',
            $store->urlFor('asset/variant/Harbour Dawn.avif'),
        );
    }

    #[Test]
    public function hasNoUrlsWhenTheFilesystemHasNone(): void
    {
        static::assertNull((new FsVariantStore($this->fs(null), onSiteDomain: false))->urlFor('a.avif'));
    }

    #[DataProvider('siteDomainProvider')]
    #[Test]
    public function knowsWhetherFilesAreOnTheSitesDomain(?string $rootUrl, bool $expected): void
    {
        static::assertSame(
            $expected,
            FsVariantStore::forFilesystem($this->fs($rootUrl), siteUrl: 'https://www.example.test/')->isOnSiteDomain(),
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function passesReadsAndChecksThrough(): void
    {
        $fs = $this->createStub(FsInterface::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('read')->willReturn('bytes');
        $store = new FsVariantStore($fs, onSiteDomain: true);

        static::assertSame([true, 'bytes'], [$store->exists('a.avif'), $store->read('a.avif')]);
    }

    /**
     * @throws StorageFailedException
     */
    #[DataProvider('failingOperationProvider')]
    #[Test]
    public function reportsFilesystemErrorsAsStorageFailures(string $operation): void
    {
        $fs = $this->createStub(FsInterface::class);
        $fs->method($operation)->willThrowException(new FsException('Disk full.'));
        $store = new FsVariantStore($fs, onSiteDomain: true);

        $this->expectException(StorageFailedException::class);

        match ($operation) {
            'fileExists' => $store->exists('a.avif'),
            'read'       => $store->read('a.avif'),
            default      => $store->write('a.avif', contents: 'bytes'),
        };
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function writesThroughToTheFilesystem(): void
    {
        $contents = 'bytes';
        $fs       = $this->createMock(FsInterface::class);
        $fs->expects(static::once())->method('write')->with('a.avif', $contents);

        (new FsVariantStore($fs, onSiteDomain: true))->write('a.avif', contents: 'bytes');
    }

    private function fs(?string $rootUrl): FsInterface
    {
        $fs = $this->createStub(FsInterface::class);
        $fs->method('getRootUrl')->willReturn($rootUrl);

        return $fs;
    }
}
