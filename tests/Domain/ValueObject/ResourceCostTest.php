<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\ResourceCost;
use App\Domain\ValueObject\ResourceStock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResourceCostTest extends TestCase
{
    public function testItNormalizesFromArrayAndSupportsMapping(): void
    {
        $cost = ResourceCost::fromArray(['metal' => 123.7, 'crystal' => 50]);

        self::assertSame(['metal' => 124, 'crystal' => 50], $cost->toArray());

        $discounted = $cost->map(static fn (int $amount) => $amount * 0.5);
        self::assertSame(['metal' => 62, 'crystal' => 25], $discounted->toArray());
    }

    public function testItRejectsInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ResourceCost::fromArray(['metal' => 'invalid']);
    }

    public function testItCanBeCreatedFromStock(): void
    {
        $stock = ResourceStock::fromArray(['metal' => 10]);
        $cost = ResourceCost::fromStock($stock);

        self::assertSame(['metal' => 10], $cost->toArray());
    }
}
