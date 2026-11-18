<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use craft\elements\Asset;
use craft\events\ModelEvent;
use parallelogram\images\craft\NewFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NewFile::class)]
#[Group('unit')]
final class NewFileTest extends TestCase
{
    /**
     * @return array<string, array{bool, string, bool}>
     */
    public static function saveProvider(): array
    {
        return [
            'a new upload'                => [true, Asset::SCENARIO_CREATE, true],
            'a replaced file'             => [false, Asset::SCENARIO_REPLACE, true],
            'an edit that keeps the file' => [false, Asset::SCENARIO_DEFAULT, false],
        ];
    }

    #[DataProvider('saveProvider')]
    #[Test]
    public function knowsWhetherASaveStoredAFile(bool $isNew, string $scenario, bool $expected): void
    {
        $asset = $this->createStub(Asset::class);
        $asset->method('getScenario')->willReturn($scenario);

        static::assertSame($expected, NewFile::arrived($asset, new ModelEvent(['isNew' => $isNew])));
    }
}
