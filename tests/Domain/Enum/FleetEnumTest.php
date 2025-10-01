<?php

declare(strict_types=1);

namespace App\Tests\Domain\Enum;

use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FleetEnumTest extends TestCase
{
    public function testMissionFromString(): void
    {
        $mission = FleetMission::fromString('TRANSPORT');

        self::assertSame(FleetMission::Transport, $mission);
        self::assertFalse($mission->isIdle());
    }

    public function testStatusHelpers(): void
    {
        $status = FleetStatus::fromString('returning');

        self::assertTrue($status->isActive());
        self::assertSame('returning', $status->value);
    }

    public function testInvalidMissionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FleetMission::fromString('unknown');
    }
}
