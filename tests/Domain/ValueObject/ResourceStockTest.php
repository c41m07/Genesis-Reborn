<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\ResourceStock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResourceStockTest extends TestCase
{
    public function testItStoresQuantities(): void
    {
        $stock = ResourceStock::fromArray(['metal' => 100, 'crystal' => 50]);

        self::assertSame(100, $stock->amount('metal'));
        self::assertSame(50, $stock->amount('crystal'));
        self::assertSame(0, $stock->amount('hydrogen'));
        self::assertSame(['metal' => 100, 'crystal' => 50], $stock->toArray());
    }

    public function testItRejectsInvalidData(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ResourceStock::fromArray(['metal' => -10]);
    }
}
