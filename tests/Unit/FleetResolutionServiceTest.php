<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\FleetResolutionService;
use App\Infrastructure\Config\BalanceConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FleetResolutionServiceTest extends TestCase
{
    private FleetResolutionService $service;

    /** @var array<string, array<string, mixed>> */
    private array $ships;

    protected function setUp(): void
    {
        $loader = new BalanceConfigLoader(dirname(__DIR__, 2) . '/config/balance');
        $this->ships = $loader->loadShips();
        $this->service = new FleetResolutionService();
    }

    public function testAttackerOverwhelmsDefendersSnapshot(): void
    {
        $result = $this->service->resolveBattle(
            ['battlecruiser' => 4, 'heavy_cruiser' => 3, 'destroyer' => 6],
            ['fighter' => 40, 'frigate' => 10, 'battleship' => 2],
            $this->ships,
            6
        );

        self::assertSame('attacker', $result['outcome']);
        self::assertSame(['destroyer' => 1, 'heavy_cruiser' => 1], $result['attacker_remaining']);
        self::assertSame([], $result['defender_remaining']);
        self::assertSame([
            'battlecruiser' => 4,
            'battleship' => 0,
            'destroyer' => 5,
            'fighter' => 0,
            'frigate' => 0,
            'heavy_cruiser' => 2,
        ], $result['attacker_losses']);
        self::assertSame([
            'battlecruiser' => 0,
            'battleship' => 2,
            'destroyer' => 0,
            'fighter' => 40,
            'frigate' => 10,
            'heavy_cruiser' => 0,
        ], $result['defender_losses']);

        $firstRound = $result['rounds'][1];
        self::assertSame(1890, $firstRound['attacker']['attack']);
        self::assertSame(1620, $firstRound['attacker']['defense']);
        self::assertSame([
            'battlecruiser' => 4,
            'destroyer' => 5,
            'heavy_cruiser' => 2,
        ], $firstRound['attacker']['losses']);
        self::assertSame(1230, $firstRound['defender']['attack']);
        self::assertSame(1120, $firstRound['defender']['defense']);
        self::assertSame([
            'battleship' => 2,
            'fighter' => 40,
            'frigate' => 10,
        ], $firstRound['defender']['losses']);
    }

    public function testDefenderHoldsLineAgainstSmallerForce(): void
    {
        $result = $this->service->resolveBattle(
            ['fighter' => 5, 'bomber' => 2],
            ['destroyer' => 4, 'heavy_cruiser' => 2],
            $this->ships,
            6
        );

        self::assertSame('defender', $result['outcome']);
        self::assertSame([], $result['attacker_remaining']);
        self::assertSame([
            'destroyer' => 3,
            'heavy_cruiser' => 2,
        ], $result['defender_remaining']);
        self::assertSame([
            'bomber' => 2,
            'destroyer' => 0,
            'fighter' => 5,
            'heavy_cruiser' => 0,
        ], $result['attacker_losses']);
        self::assertSame([
            'bomber' => 0,
            'destroyer' => 1,
            'fighter' => 0,
            'heavy_cruiser' => 0,
        ], $result['defender_losses']);

        $firstRound = $result['rounds'][1];
        self::assertSame(60, $firstRound['attacker']['attack']);
        self::assertSame(27, $firstRound['attacker']['defense']);
        self::assertSame(620, $firstRound['defender']['attack']);
        self::assertSame(520, $firstRound['defender']['defense']);
    }

    public function testMutualDestructionYieldsStalemateSnapshot(): void
    {
        $result = $this->service->resolveBattle(
            ['fighter' => 20, 'bomber' => 8, 'destroyer' => 3],
            ['fighter' => 15, 'frigate' => 4, 'light_cruiser' => 2],
            $this->ships,
            6
        );

        self::assertSame('stalemate', $result['outcome']);
        self::assertSame([], $result['attacker_remaining']);
        self::assertSame([], $result['defender_remaining']);
        self::assertSame([
            'bomber' => 8,
            'destroyer' => 3,
            'fighter' => 20,
            'frigate' => 0,
            'light_cruiser' => 0,
        ], $result['attacker_losses']);
        self::assertSame([
            'bomber' => 0,
            'destroyer' => 0,
            'fighter' => 15,
            'frigate' => 4,
            'light_cruiser' => 2,
        ], $result['defender_losses']);

        $firstRound = $result['rounds'][1];
        self::assertSame(480, $firstRound['attacker']['attack']);
        self::assertSame(288, $firstRound['attacker']['defense']);
        self::assertSame(530, $firstRound['defender']['attack']);
        self::assertSame(427, $firstRound['defender']['defense']);
    }

    public function testResolveBattleRejectsInvalidRoundLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->resolveBattle([], [], $this->ships, 0);
    }
}
