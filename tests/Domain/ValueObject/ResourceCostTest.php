<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\ResourceCost;
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

}
