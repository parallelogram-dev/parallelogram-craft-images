<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\delivery;

use parallelogram\images\delivery\Delivery;
use parallelogram\images\delivery\DeliveryOutcome;
use parallelogram\images\delivery\DeliveryPorts;
use parallelogram\images\delivery\GenerationFailedException;
use parallelogram\images\delivery\MakeResult;
use parallelogram\images\delivery\StorageFailedException;
use parallelogram\images\delivery\VariantMaker;
use parallelogram\images\delivery\VariantRequestHandler;
use parallelogram\images\sizing\Box;
use parallelogram\images\sizing\Encoding;
use parallelogram\images\sizing\Fit;
use parallelogram\images\sizing\Format;
use parallelogram\images\sizing\QualityTable;
use parallelogram\images\sizing\SizeSet;
use parallelogram\images\sizing\SizeSetCatalogue;
use parallelogram\images\sizing\Variant;
use parallelogram\images\test\TestAsset\Factory\SizeSetFactory;
use parallelogram\images\test\TestAsset\Factory\SourceImageFactory;
use parallelogram\images\test\TestAsset\Fake\InMemoryVariantStore;
use parallelogram\images\test\TestAsset\Fake\RecordingGenerator;
use parallelogram\images\test\TestAsset\Fake\ScriptedLock;
use parallelogram\images\test\TestAsset\Fake\StaticSourceLookup;
use parallelogram\images\variants\Dimensions;
use parallelogram\images\variants\FocalPoint;
use parallelogram\images\variants\ParsedVariantPath;
use parallelogram\images\variants\Processing;
use parallelogram\images\variants\SourceImage;
use parallelogram\images\variants\VariantFile;
use parallelogram\images\variants\VariantHasher;
use parallelogram\images\variants\VariantPath;
use parallelogram\images\variants\VariantPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(VariantRequestHandler::class)]
#[CoversClass(Delivery::class)]
#[CoversClass(DeliveryPorts::class)]
#[CoversClass(GenerationFailedException::class)]
#[CoversClass(StorageFailedException::class)]
#[UsesClass(Box::class)]
#[UsesClass(Dimensions::class)]
#[UsesClass(Encoding::class)]
#[UsesClass(Fit::class)]
#[UsesClass(FocalPoint::class)]
#[UsesClass(Format::class)]
#[UsesClass(ParsedVariantPath::class)]
#[UsesClass(Processing::class)]
#[UsesClass(QualityTable::class)]
#[UsesClass(SizeSet::class)]
#[UsesClass(SizeSetCatalogue::class)]
#[UsesClass(SourceImage::class)]
#[UsesClass(Variant::class)]
#[UsesClass(VariantFile::class)]
#[UsesClass(VariantHasher::class)]
#[UsesClass(VariantPath::class)]
#[UsesClass(VariantPlanner::class)]
#[UsesClass(MakeResult::class)]
#[UsesClass(VariantMaker::class)]
#[Group('unit')]
final class VariantRequestHandlerTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function unknownRequestProvider(): array
    {
        $uid = SourceImageFactory::UID;

        return [
            'malformed path' => ['asset/variant/not-a-variant.jpg'],
            'unknown asset'  => [
                'asset/variant/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa/0123456789abcdef/harbour-dawn.avif',
            ],
            'undefined size' => ["asset/variant/{$uid}/0123456789abcdef/harbour-dawn.avif"],
        ];
    }

    private static function catalogue(): SizeSetCatalogue
    {
        return new SizeSetCatalogue([SizeSetFactory::make(widths: [480, 960])]);
    }

    private static function firstFile(): VariantFile
    {
        $files = self::planner()->filesFor(SourceImageFactory::make(), SizeSetFactory::make(widths: [480, 960]));

        return $files[0] ?? static::fail('Expected at least one file.');
    }

    private static function handler(
        InMemoryVariantStore $store,
        RecordingGenerator $generator = new RecordingGenerator(),
        ScriptedLock $lock = new ScriptedLock(),
    ): VariantRequestHandler {
        return new VariantRequestHandler(
            new VariantPath('asset/variant'),
            self::planner(),
            self::catalogue(),
            new DeliveryPorts(new StaticSourceLookup([SourceImageFactory::make()]), $store, $generator, $lock),
        );
    }

    private static function planner(): VariantPlanner
    {
        return new VariantPlanner(
            new VariantHasher('secret', new Processing()),
            new VariantPath('asset/variant'),
            new QualityTable(),
        );
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function doesNotGenerateAFileThatAlreadyExists(): void
    {
        $store     = new InMemoryVariantStore();
        $generator = new RecordingGenerator();
        $path      = self::firstFile()->path;
        $store->write($path, contents: 'existing bytes');

        $delivery = self::handler($store, $generator)->handle($path);

        static::assertEquals([Delivery::content('existing bytes', mimeType: 'image/avif'), []], [
            $delivery,
            $generator->generated,
        ]);
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function doesNotRegenerateAFileAnotherRequestFinishedWhileWaiting(): void
    {
        $store     = new InMemoryVariantStore();
        $generator = new RecordingGenerator();
        $path      = self::firstFile()->path;
        $lock      = new ScriptedLock(
            free: true,
            onAcquire: /**
             * @throws StorageFailedException
             */
            static function () use ($store, $path): void {
                $store->write($path, contents: 'made elsewhere');
            },
        );

        $delivery = self::handler($store, $generator, $lock)->handle($path);

        static::assertEquals([Delivery::content('made elsewhere', mimeType: 'image/avif'), []], [
            $delivery,
            $generator->generated,
        ]);
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function generatesSavesAndSendsAMissingFile(): void
    {
        $store = new InMemoryVariantStore();
        $path  = self::firstFile()->path;

        $delivery = self::handler($store)->handle("/{$path}");

        static::assertEquals(
            [Delivery::content("bytes of {$path}", mimeType: 'image/avif'), ["bytes of {$path}"]],
            [$delivery, [$store->read($path)]],
        );
    }

    #[Test]
    public function redirectsToFilesKeptElsewhere(): void
    {
        $store = new InMemoryVariantStore('https://cdn.example.test', onSiteDomain: false);
        $path  = self::firstFile()->path;

        $delivery = self::handler($store)->handle($path);

        static::assertEquals(Delivery::redirect("https://cdn.example.test/{$path}"), $delivery);
    }

    #[Test]
    public function releasesTheLockWhenGenerationFails(): void
    {
        $lock = new ScriptedLock();
        $path = self::firstFile()->path;

        $delivery = self::handler(new InMemoryVariantStore(), new RecordingGenerator(fails: true), $lock)
            ->handle($path);

        static::assertEquals([Delivery::unavailable(), [$path]], [$delivery, $lock->released]);
    }

    #[DataProvider('unknownRequestProvider')]
    #[Test]
    public function reportsRequestsForVariantsThatDoNotExistAsNotFound(string $path): void
    {
        static::assertSame(
            DeliveryOutcome::NotFound,
            self::handler(new InMemoryVariantStore())->handle($path)->outcome,
        );
    }

    #[Test]
    public function sendsTheFileWhenItsFilesystemHasNoUrls(): void
    {
        $store = new InMemoryVariantStore(null, onSiteDomain: false);
        $path  = self::firstFile()->path;

        static::assertSame(DeliveryOutcome::Content, self::handler($store)->handle($path)->outcome);
    }

    /**
     * @throws StorageFailedException
     */
    #[Test]
    public function servesAFileThatAppearedWhileTheLockWasHeld(): void
    {
        $store = new InMemoryVariantStore();
        $path  = self::firstFile()->path;
        $lock  = new ScriptedLock(
            free: false,
            onAcquire: /**
             * @throws StorageFailedException
             */
            static function () use ($store, $path): void {
                $store->write($path, contents: 'made elsewhere');
            },
        );

        static::assertEquals(
            Delivery::content('made elsewhere', mimeType: 'image/avif'),
            self::handler($store, new RecordingGenerator(), $lock)->handle($path),
        );
    }

    #[Test]
    public function tellsTheVisitorToRetryWhenAnotherRequestIsStillGenerating(): void
    {
        $delivery = self::handler(new InMemoryVariantStore(), new RecordingGenerator(), new ScriptedLock(free: false))
            ->handle(self::firstFile()->path);

        static::assertEquals(Delivery::unavailable(), $delivery);
    }

    #[Test]
    public function tellsTheVisitorToRetryWhenStorageFails(): void
    {
        $store = new InMemoryVariantStore(broken: true);

        static::assertEquals(Delivery::unavailable(), self::handler($store)->handle(self::firstFile()->path));
    }
}
