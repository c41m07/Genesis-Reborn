<?php

namespace App\Tests\Domain\Service;

use App\Domain\Battle\DTO\AttackingFleetDTO;
use App\Domain\Battle\DTO\DefendingFleetDTO;
use App\Domain\Battle\DTO\FleetBattleResultDTO;
use App\Domain\Service\FleetResolutionService;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Config\BalanceConfigLoader;
use PHPUnit\Framework\TestCase;

final class FleetResolutionServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        $this->temporaryFiles = [];
    }

    public function testResolveBattleReturnsAttackerVictoryWhenDefenderIsEmpty(): void
    {
        $catalog = $this->createCatalog();
        $loader = $this->createLoader($this->buildConfig());
        $service = new FleetResolutionService($catalog, $loader);

        $result = $service->resolveBattle(
            new AttackingFleetDTO(['fighter' => 5]),
            new DefendingFleetDTO([])
        );

        self::assertInstanceOf(FleetBattleResultDTO::class, $result);
        self::assertSame('attacker', $result->getWinner());
        self::assertSame(['fighter' => 5], $result->getAttackerRemaining());
        self::assertSame([], $result->getDefenderRemaining());
        self::assertCount(0, $result->getRounds());
        self::assertFalse($result->didAttackerRetreat());
        self::assertFalse($result->didDefenderRetreat());
    }

    public function testResolveBattleConsumesMultipleRoundsUntilDefenderDestroyed(): void
    {
        $catalog = $this->createCatalog();
        $loader = $this->createLoader($this->buildConfig());
        $service = new FleetResolutionService($catalog, $loader);

        $result = $service->resolveBattle(
            new AttackingFleetDTO(['fighter' => 10]),
            new DefendingFleetDTO(['fighter' => 5])
        );

        self::assertSame('attacker', $result->getWinner());
        self::assertSame(['fighter' => 10], $result->getAttackerRemaining());
        self::assertSame(['fighter' => 0], $result->getDefenderRemaining());
        self::assertSame(5, $result->getRoundsFought());
        self::assertFalse($result->didAttackerRetreat());
        self::assertFalse($result->didDefenderRetreat());

        $rounds = $result->getRounds();
        self::assertNotEmpty($rounds);
        self::assertSame(['fighter' => 1], $rounds[0]->getDefenderLosses());
    }

    public function testMinimumLossRatioForcesAttritionWhenDamageIsInsufficient(): void
    {
        $catalog = $this->createCatalog(['attaque' => 0]);
        $loader = $this->createLoader($this->buildConfig(minLossRatio: 0.5));
        $service = new FleetResolutionService($catalog, $loader);

        $result = $service->resolveBattle(
            new AttackingFleetDTO(['fighter' => 2]),
            new DefendingFleetDTO(['fighter' => 2])
        );

        self::assertSame('draw', $result->getWinner());
        self::assertSame(['fighter' => 0], $result->getAttackerRemaining());
        self::assertSame(['fighter' => 0], $result->getDefenderRemaining());
        self::assertSame(2, $result->getRoundsFought());
    }

    public function testRetreatThresholdStopsBattle(): void
    {
        $catalog = $this->createCatalog();
        $loader = $this->createLoader($this->buildConfig(defenderRetreat: 0.9));
        $service = new FleetResolutionService($catalog, $loader);

        $result = $service->resolveBattle(
            new AttackingFleetDTO(['fighter' => 8]),
            new DefendingFleetDTO(['fighter' => 4])
        );

        self::assertSame('attacker', $result->getWinner());
        self::assertTrue($result->didDefenderRetreat());
        self::assertSame(1, $result->getRoundsFought());
    }

    /**
     * @param array{attaque?: int, défense?: int} $statOverride
     */
    private function createCatalog(array $statOverride = []): ShipCatalog
    {
        $stats = array_merge(
            ['attaque' => 10, 'défense' => 5],
            $statOverride
        );

        return new ShipCatalog([
            'fighter' => [
                'label' => 'Fighter',
                'category' => 'Test',
                'role' => '',
                'description' => '',
                'base_cost' => [],
                'build_time' => 0,
                'stats' => $stats,
                'requires_research' => [],
                'image' => '',
            ],
        ]);
    }

    private function createLoader(string $yaml): BalanceConfigLoader
    {
        $path = tempnam(sys_get_temp_dir(), 'balance_');
        if ($path === false) {
            self::fail('Unable to create temporary configuration file.');
        }

        if (file_put_contents($path, $yaml) === false) {
            self::fail('Unable to write temporary configuration file.');
        }

        $this->temporaryFiles[] = $path;

        return new BalanceConfigLoader($path);
    }

    private function buildConfig(float $minLossRatio = 0.0, float $attackerRetreat = 0.0, float $defenderRetreat = 0.0): string
    {
        return <<<YAML
combat:
  rounds: 6
  min_losses_ratio: {$minLossRatio}
  retreat_threshold:
    attacker: {$attackerRetreat}
    defender: {$defenderRetreat}
  stat_multipliers:
    attack: 1.0
    hull_per_defense: 5.0
    base_hull: 5.0
  attack_multipliers:
    attacker: 1.0
    defender: 1.0
  default_damage_modifier: 1.0
target_priorities:
  default:
    - fighter
damage_modifiers:
  default: 1.0
YAML;
    }
}
