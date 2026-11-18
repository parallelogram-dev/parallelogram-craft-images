<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\remote;

use parallelogram\images\remote\ImgproxySigner;
use parallelogram\images\variants\InvalidVariantException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ImgproxySigner::class)]
#[UsesClass(InvalidVariantException::class)]
#[Group('unit')]
final class ImgproxySignerTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidHexProvider(): array
    {
        return [
            'empty key'    => ['', '68656C6C6F', 'The imgproxy key must be hex'],
            'odd key'      => ['abc', '68656C6C6F', 'The imgproxy key must be hex'],
            'non-hex salt' => ['736563726574', 'hello', 'The imgproxy salt must be hex'],
        ];
    }

    /**
     * The worked example from imgproxy's own signing documentation.
     */
    #[Test]
    public function matchesImgproxysDocumentedExample(): void
    {
        $signer = new ImgproxySigner('736563726574', saltHex: '68656C6C6F');

        static::assertSame(
            'oKfUtW34Dvo2BGQehJFR4Nr0_rIjOtdtzJ3QFsUcXH8',
            $signer->sign('/rs:fill:300:400:0/g:sm/aHR0cDovL2V4YW1w/bGUuY29tL2ltYWdl/cy9jdXJpb3NpdHku/anBn.png'),
        );
    }

    #[DataProvider('invalidHexProvider')]
    #[Test]
    public function refusesKeysAndSaltsThatAreNotHex(string $key, string $salt, string $message): void
    {
        $this->expectException(InvalidVariantException::class);
        $this->expectExceptionMessage($message);

        new ImgproxySigner($key, $salt);
    }
}
