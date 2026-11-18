<?php

declare(strict_types=1);

namespace parallelogram\images\test\Unit\craft;

use parallelogram\images\craft\SecretProvisioner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SecretProvisioner::class)]
#[Group('unit')]
final class SecretProvisionerTest extends TestCase
{
    /**
     * @var list<array{string, string}>
     */
    private array $written = [];

    /**
     * @return array<string, array{string|null}>
     */
    public static function missingSecretProvider(): array
    {
        return [
            'unset' => [null],
            'empty' => [''],
        ];
    }

    #[Test]
    public function leavesAnExistingSecretAlone(): void
    {
        static::assertSame([false, []], [$this->provisioner('already-set')->provision(), $this->written]);
    }

    #[DataProvider('missingSecretProvider')]
    #[Test]
    public function writesANewSecretWhenNoneIsSet(?string $current): void
    {
        static::assertSame(
            [true, [['P_IMAGES_SECRET', 'generated']]],
            [$this->provisioner($current)->provision(), $this->written],
        );
    }

    private function provisioner(?string $current): SecretProvisioner
    {
        return new SecretProvisioner(
            static fn(): ?string => $current,
            function (string $name, string $value): void {
                $this->written[] = [$name, $value];
            },
            static fn(): string => 'generated',
        );
    }
}
