<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Battle\DTO\AttackingFleetDTO;
use App\Domain\Battle\DTO\DefendingFleetDTO;
use App\Domain\Battle\DTO\FleetBattleResultDTO;
use App\Domain\Service\FleetResolutionService;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Config\BalanceConfigLoader;
use PHPUnit\Framework\TestCase;

final class FleetResolutionServiceTest extends TestCase
{
    private FleetResolutionService $service;

    protected function setUp(): void
    {
        $loader = new BalanceConfigLoader(dirname(__DIR__, 2) . '/config/balance');
        $catalog = new ShipCatalog($loader->getShipConfigs());
        $this->service = new FleetResolutionService($catalog, $loader);
    }

    public function testResolveBattleReturnsAttackerVictoryWhenDefenderEmpty(): void
    {
        $result = $this->service->resolveBattle(
            new AttackingFleetDTO(['fighter' => 5]),
            new DefendingFleetDTO([])
        );

        self::assertInstanceOf(FleetBattleResultDTO::class, $result);
        self::assertSame('attacker', $result->getWinner());
        self::assertSame(['fighter' => 5], $result->getAttackerRemaining());
        self::assertSame([], $result->getDefenderRemaining());
        self::assertSame(0, $result->getRoundsFought());
    }

    public function testResolveBattleDrawWhenBothSidesEmpty(): void
    {
        $result = $this->service->resolveBattle(
            new AttackingFleetDTO([]),
            new DefendingFleetDTO([])
        );

        self::assertSame('draw', $result->getWinner());
        self::assertSame([], $result->getAttackerRemaining());
        self::assertSame([], $result->getDefenderRemaining());
        self::assertSame(0, $result->getRoundsFought());
    }
}
