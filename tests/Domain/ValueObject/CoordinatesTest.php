<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\Coordinates;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CoordinatesTest extends TestCase
{
    public function testItCreatesFromArrayAndExposesValues(): void
    {
        $coordinates = Coordinates::fromArray(['galaxy' => 1, 'system' => 200, 'position' => 12]);

        self::assertSame(1, $coordinates->galaxy());
        self::assertSame(200, $coordinates->system());
        self::assertSame(12, $coordinates->position());
        self::assertSame(['galaxy' => 1, 'system' => 200, 'position' => 12], $coordinates->toArray());
    }

    public function testItRejectsNegativeValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Coordinates::fromInts(-1, 2, 3);
    }
}
