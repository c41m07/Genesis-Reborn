<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Building\UpgradeBuilding;
use App\Application\UseCase\Research\StartResearch;
use App\Application\UseCase\Shipyard\BuildShips;
use App\Application\UseCase\Shipyard\GetShipyardOverview;
use App\Controller\ResourceApiController;
use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\PlayerStatsRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\FleetNavigationService;
use App\Domain\Service\ResearchCalculator;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ResourceEffectFactory;
use App\Domain\Service\ResourceTickService;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Config\BuildingConfig;
use App\Infrastructure\Config\ShipConfig;
use App\Infrastructure\Config\TechnologyConfig;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\Session;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Persistence\PdoBuildQueueRepository;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class QueueProcessingTest extends TestCase
{
    public function testBuildingUpgradeIsQueuedAndProcessed(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 42, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['metal_mine' => 0, 'research_lab' => 2, 'shipyard' => 1],
        ]);
        $buildQueue = new InMemoryBuildQueueRepository();
        $catalog = new BuildingCatalog($this->makeBuildingConfigs([
            'metal_mine' => [
                'label' => 'Mine de métal',
                'base_cost' => ['metal' => 100],
                'growth_cost' => 1.5,
                'base_time' => 10,
                'growth_time' => 1.2,
                'prod_base' => 30,
                'prod_growth' => 1.2,
                'energy_use_base' => 5,
                'energy_use_growth' => 1.1,
                'energy_use_linear' => false,
                'affects' => 'metal',
            ],
        ]));
        $calculator = new BuildingCalculator($catalog);

        $playerStats = new InMemoryPlayerStatsRepository();
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $useCase = new UpgradeBuilding($planetRepository, $buildingStates, $buildQueue, $playerStats, $researchStates, $catalog, $calculator);
        $processor = new ProcessBuildQueue($buildQueue, $buildingStates, $planetRepository, $catalog, $calculator);

        $result = $useCase->execute(1, 42, 'metal_mine');
        self::assertTrue($result['success']);
        self::assertSame(0, $buildingStates->getLevels(1)['metal_mine']);
        self::assertSame(1, $buildQueue->countActive(1));

        $planetBefore = $planetRepository->find(1);
        self::assertNotNull($planetBefore);
        self::assertSame(0, $planetBefore->getMetalPerHour());

        $buildQueue->forceComplete(1);
        $processor->process(1);

        self::assertSame(1, $buildingStates->getLevels(1)['metal_mine']);
        self::assertSame(0, $buildQueue->countActive(1));
        $planetAfter = $planetRepository->find(1);
        self::assertNotNull($planetAfter);
        self::assertGreaterThan(0, $planetAfter->getMetalPerHour());
        self::assertSame(100, $playerStats->getBuildingSpending(42));
    }

    /**
     * @param array<string, array<string, mixed>> $config
     *
     * @return BuildingConfig[]
     */
    private function makeBuildingConfigs(array $config): array
    {
        $result = [];
        foreach ($config as $key => $data) {
            $result[] = new BuildingConfig($key, $data);
        }

        return $result;
    }

    public function testResearchStartIsQueuedAndProcessed(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 42, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['research_lab' => 3],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => ['propulsion_basic' => 0],
        ]);
        $researchQueue = new InMemoryResearchQueueRepository();
        $catalog = new ResearchCatalog($this->makeTechnologyConfigs([
            'propulsion_basic' => [
                'label' => 'Propulsion spatiale',
                'category' => 'Propulsion',
                'description' => '',
                'base_cost' => ['metal' => 120, 'crystal' => 80, 'hydrogen' => 40],
                'base_time' => 50,
                'growth_cost' => 1.65,
                'growth_time' => 1.6,
                'max_level' => 10,
                'requires' => [],
                'requires_lab' => 1,
                'image' => '',
            ],
        ]));
        $calculator = new ResearchCalculator(0.1);

        $playerStats = new InMemoryPlayerStatsRepository();
        $useCase = new StartResearch($planetRepository, $buildingStates, $researchStates, $researchQueue, $playerStats, $catalog, $calculator);
        $processor = new ProcessResearchQueue($researchQueue, $researchStates);

        $result = $useCase->execute(1, 42, 'propulsion_basic');
        self::assertTrue($result['success']);
        self::assertSame(0, $researchStates->getLevels(1)['propulsion_basic']);
        self::assertSame(1, $researchQueue->countActive(1));
        self::assertSame(38, $researchQueue->getLastDuration());

        $researchQueue->forceComplete(1);
        $processor->process(1);

        self::assertSame(1, $researchStates->getLevels(1)['propulsion_basic']);
        self::assertSame(0, $researchQueue->countActive(1));
        self::assertSame(240, $playerStats->getScienceSpending(42));
    }

    /**
     * @param array<string, array<string, mixed>> $config
     *
     * @return TechnologyConfig[]
     */
    private function makeTechnologyConfigs(array $config): array
    {
        $result = [];
        foreach ($config as $key => $data) {
            $result[] = new TechnologyConfig($key, $data);
        }

        return $result;
    }

    public function testShipProductionIsQueuedAndProcessed(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 42, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['shipyard' => 2],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $shipQueue = new InMemoryShipBuildQueueRepository();
        $fleetRepository = new InMemoryFleetRepository();
        $buildingCatalog = new BuildingCatalog($this->makeBuildingConfigs([
            'shipyard' => [
                'label' => 'Chantier',
                'base_cost' => ['metal' => 100],
                'growth_cost' => 1.0,
                'base_time' => 1,
                'growth_time' => 1.0,
                'prod_base' => 0,
                'prod_growth' => 1.0,
                'energy_use_base' => 0,
                'energy_use_growth' => 1.0,
                'energy_use_linear' => false,
                'affects' => 'energy',
                'ship_build_speed_bonus' => ['base' => 0.1, 'linear' => true, 'max' => 0.9],
            ],
        ]));
        $buildingCalculator = new BuildingCalculator();
        $catalog = new ShipCatalog($this->makeShipConfigs([
            'fighter' => [
                'label' => 'Chasseur',
                'category' => 'Escadre',
                'role' => 'Intercepteur',
                'description' => '',
                'base_cost' => ['metal' => 200],
                'build_time' => 4,
                'stats' => [],
                'requires_research' => [],
                'image' => '',
            ],
        ]));

        $playerStats = new InMemoryPlayerStatsRepository();
        $useCase = new BuildShips($planetRepository, $buildingStates, $researchStates, $shipQueue, $playerStats, $buildingCatalog, $buildingCalculator, $catalog);
        $processor = new ProcessShipBuildQueue($shipQueue, $fleetRepository);

        $result = $useCase->execute(1, 42, 'fighter', 3);
        self::assertTrue($result['success']);
        self::assertSame([], $fleetRepository->getFleet(1));
        self::assertSame(1, $shipQueue->countActive(1));
        $expectedPerUnit = max(1, (int)floor(4 / (1 + 0.1 * 2)));
        self::assertSame($expectedPerUnit * 3, $shipQueue->getLastDuration(1));

        $shipQueue->forceComplete(1);
        $processor->process(1);

        self::assertSame(['fighter' => 3], $fleetRepository->getFleet(1));
        self::assertSame(0, $shipQueue->countActive(1));
        self::assertSame(600, $playerStats->getFleetSpending(42));
    }

    /**
     * @param array<string, array<string, mixed>> $config
     *
     * @return ShipConfig[]
     */
    private function makeShipConfigs(array $config): array
    {
        $result = [];
        foreach ($config as $key => $data) {
            $result[] = new ShipConfig($key, $data);
        }

        return $result;
    }

    public function testShipProductionDurationRespectsMinimumPerUnit(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 84, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['shipyard' => 12],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $shipQueue = new InMemoryShipBuildQueueRepository();
        $fleetRepository = new InMemoryFleetRepository();
        $buildingCatalog = new BuildingCatalog($this->makeBuildingConfigs([
            'shipyard' => [
                'label' => 'Chantier',
                'base_cost' => ['metal' => 100],
                'growth_cost' => 1.0,
                'base_time' => 1,
                'growth_time' => 1.0,
                'prod_base' => 0,
                'prod_growth' => 1.0,
                'energy_use_base' => 0,
                'energy_use_growth' => 1.0,
                'energy_use_linear' => false,
                'affects' => 'energy',
                'ship_build_speed_bonus' => ['base' => 0.1, 'linear' => true, 'max' => 0.9],
            ],
        ]));
        $buildingCalculator = new BuildingCalculator();
        $catalog = new ShipCatalog($this->makeShipConfigs([
            'probe' => [
                'label' => 'Sonde',
                'category' => 'Divers',
                'role' => 'Exploration',
                'description' => '',
                'base_cost' => ['metal' => 20],
                'build_time' => 1,
                'stats' => [],
                'requires_research' => [],
                'image' => '',
            ],
        ]));

        $playerStats = new InMemoryPlayerStatsRepository();
        $buildShips = new BuildShips($planetRepository, $buildingStates, $researchStates, $shipQueue, $playerStats, $buildingCatalog, $buildingCalculator, $catalog);

        $result = $buildShips->execute(1, 84, 'probe', 5);
        self::assertTrue($result['success']);
        self::assertSame(1, $shipQueue->countActive(1));
        self::assertSame(5, $shipQueue->getLastDuration(1));
    }

    public function testShipyardOverviewReflectsSpeedBonus(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 55, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['shipyard' => 3],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $shipQueue = new InMemoryShipBuildQueueRepository();
        $fleetRepository = new InMemoryFleetRepository();
        $buildingCatalog = new BuildingCatalog($this->makeBuildingConfigs([
            'shipyard' => [
                'label' => 'Chantier',
                'base_cost' => ['metal' => 100],
                'growth_cost' => 1.0,
                'base_time' => 1,
                'growth_time' => 1.0,
                'prod_base' => 0,
                'prod_growth' => 1.0,
                'energy_use_base' => 0,
                'energy_use_growth' => 1.0,
                'energy_use_linear' => false,
                'affects' => 'energy',
                'ship_build_speed_bonus' => ['base' => 0.1, 'linear' => true, 'max' => 0.9],
            ],
        ]));
        $buildingCalculator = new BuildingCalculator();
        $catalog = new ShipCatalog($this->makeShipConfigs([
            'fighter' => [
                'label' => 'Chasseur',
                'category' => 'Escadre',
                'role' => 'Intercepteur',
                'description' => '',
                'base_cost' => ['metal' => 200],
                'build_time' => 10,
                'stats' => [],
                'requires_research' => [],
                'image' => '',
            ],
        ]));
        $queueProcessor = new ProcessShipBuildQueue($shipQueue, $fleetRepository);

        $overviewUseCase = new GetShipyardOverview(
            $planetRepository,
            $buildingStates,
            $researchStates,
            $shipQueue,
            $fleetRepository,
            $catalog,
            $queueProcessor,
            $buildingCatalog,
            $buildingCalculator
        );

        $overview = $overviewUseCase->execute(1);
        $shipyardDefinition = $buildingCatalog->get('shipyard');
        $expectedBonus = $buildingCalculator->shipBuildSpeedBonus($shipyardDefinition, 3);
        $expectedBuildTime = $buildingCalculator->applyShipBuildSpeedBonus($shipyardDefinition, 3, 10);

        $foundBuildTime = null;
        $foundBaseTime = null;
        foreach ($overview['categories'] as $category) {
            foreach ($category['items'] as $item) {
                $definition = $item['definition'];
                if ($definition->getKey() === 'fighter') {
                    $foundBuildTime = $item['buildTime'] ?? null;
                    $foundBaseTime = $item['baseBuildTime'] ?? null;
                    break 2;
                }
            }
        }

        self::assertNotNull($foundBuildTime);
        self::assertNotNull($foundBaseTime);
        self::assertSame($expectedBuildTime, $foundBuildTime);
        self::assertSame(10, $foundBaseTime);
        self::assertEqualsWithDelta($expectedBonus, $overview['shipyardBonus'], 0.0001);
    }

    public function testBuildingQueueSequentialTargets(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 99, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['metal_mine' => 0, 'research_lab' => 2, 'shipyard' => 1],
        ]);
        $buildQueue = new InMemoryBuildQueueRepository();
        $playerStats = new InMemoryPlayerStatsRepository();
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $catalog = new BuildingCatalog($this->makeBuildingConfigs([
            'metal_mine' => [
                'label' => 'Mine de métal',
                'base_cost' => ['metal' => 100],
                'growth_cost' => 1.5,
                'base_time' => 10,
                'growth_time' => 1.2,
                'prod_base' => 30,
                'prod_growth' => 1.2,
                'energy_use_base' => 5,
                'energy_use_growth' => 1.1,
                'energy_use_linear' => false,
                'affects' => 'metal',
            ],
        ]));
        $calculator = new BuildingCalculator($catalog);

        $useCase = new UpgradeBuilding($planetRepository, $buildingStates, $buildQueue, $playerStats, $researchStates, $catalog, $calculator);
        $useCase->execute(1, 99, 'metal_mine');
        $useCase->execute(1, 99, 'metal_mine');
        $useCase->execute(1, 99, 'metal_mine');

        $jobs = $buildQueue->getActiveQueue(1);
        usort($jobs, static fn ($a, $b) => $a->getTargetLevel() <=> $b->getTargetLevel());
        $targets = array_map(static fn ($job) => $job->getTargetLevel(), $jobs);

        self::assertSame([1, 2, 3], $targets);
        $endTimes = array_map(static fn ($job) => $job->getEndsAt()->getTimestamp(), $jobs);
        self::assertTrue($endTimes[0] < $endTimes[1]);
        self::assertTrue($endTimes[1] < $endTimes[2]);
    }

    public function testPdoBuildQueueAlignsJobsSequentially(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('NOW', static fn (): string => (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        }

        $pdo->exec('CREATE TABLE players (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT)');
        $pdo->exec('CREATE TABLE planets (id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE build_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER NOT NULL,
            planet_id INTEGER NOT NULL,
            bkey TEXT NOT NULL,
            target_level INTEGER NOT NULL,
            ends_at TEXT NOT NULL
        )');

        $pdo->exec("INSERT INTO players (id, username) VALUES (1, 'Commander')");
        $pdo->exec('INSERT INTO planets (id, player_id) VALUES (1, 1)');

        $repository = new PdoBuildQueueRepository($pdo);

        $durations = [12, 18, 9];
        foreach ($durations as $index => $duration) {
            $repository->enqueue(1, 'metal_mine', $index + 1, $duration);
        }

        $jobs = array_values($repository->getActiveQueue(1));
        self::assertCount(count($durations), $jobs);

        $previousEnd = null;
        foreach ($jobs as $index => $job) {
            $startAt = $job->getEndsAt()->sub(new \DateInterval('PT' . $durations[$index] . 'S'));

            if ($previousEnd !== null) {
                self::assertGreaterThanOrEqual($previousEnd->getTimestamp(), $startAt->getTimestamp());
                self::assertSame(
                    $previousEnd->format('Y-m-d H:i:s'),
                    $startAt->format('Y-m-d H:i:s')
                );
            }

            $previousEnd = $job->getEndsAt();
        }
    }

    public function testBuildingQueueRejectsWhenFull(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 77, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['metal_mine' => 0, 'research_lab' => 2, 'shipyard' => 1],
        ]);
        $buildQueue = new InMemoryBuildQueueRepository();
        $playerStats = new InMemoryPlayerStatsRepository();
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $catalog = new BuildingCatalog($this->makeBuildingConfigs([
            'metal_mine' => [
                'label' => 'Mine de métal',
                'base_cost' => ['metal' => 50],
                'growth_cost' => 1.5,
                'base_time' => 5,
                'growth_time' => 1.1,
                'prod_base' => 10,
                'prod_growth' => 1.2,
                'energy_use_base' => 2,
                'energy_use_growth' => 1.05,
                'energy_use_linear' => false,
                'affects' => 'metal',
            ],
        ]));
        $calculator = new BuildingCalculator($catalog);

        $useCase = new UpgradeBuilding($planetRepository, $buildingStates, $buildQueue, $playerStats, $researchStates, $catalog, $calculator);
        for ($i = 0; $i < 5; ++$i) {
            $result = $useCase->execute(1, 77, 'metal_mine');
            self::assertTrue($result['success']);
        }

        $sixth = $useCase->execute(1, 77, 'metal_mine');
        self::assertFalse($sixth['success']);
        self::assertSame('La file de construction est pleine (5 actions maximum).', $sixth['message']);
        self::assertSame(5, $buildQueue->countActive(1));
    }

    public function testBuildingQueueAdvancesAfterCompletion(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 55, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['metal_mine' => 0, 'research_lab' => 2, 'shipyard' => 1],
        ]);
        $buildQueue = new InMemoryBuildQueueRepository();
        $playerStats = new InMemoryPlayerStatsRepository();
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $catalog = new BuildingCatalog($this->makeBuildingConfigs([
            'metal_mine' => [
                'label' => 'Mine de métal',
                'base_cost' => ['metal' => 100],
                'growth_cost' => 1.5,
                'base_time' => 10,
                'growth_time' => 1.2,
                'prod_base' => 30,
                'prod_growth' => 1.2,
                'energy_use_base' => 5,
                'energy_use_growth' => 1.1,
                'energy_use_linear' => false,
                'affects' => 'metal',
            ],
        ]));
        $calculator = new BuildingCalculator($catalog);
        $useCase = new UpgradeBuilding($planetRepository, $buildingStates, $buildQueue, $playerStats, $researchStates, $catalog, $calculator);
        $processor = new ProcessBuildQueue($buildQueue, $buildingStates, $planetRepository, $catalog, $calculator);

        $useCase->execute(1, 55, 'metal_mine');
        $useCase->execute(1, 55, 'metal_mine');

        $buildQueue->forceCompleteNext(1);
        $processor->process(1);

        self::assertSame(1, $buildingStates->getLevels(1)['metal_mine']);
        $remainingJobs = $buildQueue->getActiveQueue(1);
        self::assertCount(1, $remainingJobs);
        self::assertSame(2, $remainingJobs[0]->getTargetLevel());
    }

    public function testResearchQueueSequentialTargets(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 11, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['research_lab' => 3],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => ['propulsion_basic' => 0],
        ]);
        $researchQueue = new InMemoryResearchQueueRepository();
        $playerStats = new InMemoryPlayerStatsRepository();
        $catalog = new ResearchCatalog($this->makeTechnologyConfigs([
            'propulsion_basic' => [
                'label' => 'Propulsion spatiale',
                'category' => 'Propulsion',
                'description' => '',
                'base_cost' => ['metal' => 120, 'crystal' => 80],
                'base_time' => 50,
                'growth_cost' => 1.5,
                'growth_time' => 2.0,
                'max_level' => 5,
                'requires' => [],
                'requires_lab' => 1,
                'image' => '',
            ],
        ]));
        $calculator = new ResearchCalculator(0.1);
        $useCase = new StartResearch($planetRepository, $buildingStates, $researchStates, $researchQueue, $playerStats, $catalog, $calculator);

        $useCase->execute(1, 11, 'propulsion_basic');
        $useCase->execute(1, 11, 'propulsion_basic');
        $useCase->execute(1, 11, 'propulsion_basic');

        $jobs = $researchQueue->getActiveQueue(1);
        usort($jobs, static fn ($a, $b) => $a->getTargetLevel() <=> $b->getTargetLevel());
        $targets = array_map(static fn ($job) => $job->getTargetLevel(), $jobs);

        self::assertSame([1, 2, 3], $targets);
    }

    public function testResearchQueueRejectsWhenFull(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 21, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['research_lab' => 3],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => ['propulsion_basic' => 0],
        ]);
        $researchQueue = new InMemoryResearchQueueRepository();
        $playerStats = new InMemoryPlayerStatsRepository();
        $catalog = new ResearchCatalog($this->makeTechnologyConfigs([
            'propulsion_basic' => [
                'label' => 'Propulsion spatiale',
                'category' => 'Propulsion',
                'description' => '',
                'base_cost' => ['metal' => 50],
                'base_time' => 4,
                'growth_cost' => 1.5,
                'growth_time' => 2.0,
                'max_level' => 0,
                'requires' => [],
                'requires_lab' => 1,
                'image' => '',
            ],
        ]));
        $calculator = new ResearchCalculator(0.1);
        $useCase = new StartResearch($planetRepository, $buildingStates, $researchStates, $researchQueue, $playerStats, $catalog, $calculator);

        for ($i = 0; $i < 5; ++$i) {
            $result = $useCase->execute(1, 21, 'propulsion_basic');
            self::assertTrue($result['success']);
        }

        $sixth = $useCase->execute(1, 21, 'propulsion_basic');
        self::assertFalse($sixth['success']);
        self::assertSame('La file de recherche est pleine (5 programmes maximum).', $sixth['message']);
        self::assertSame(5, $researchQueue->countActive(1));
    }

    public function testResourceApiTicksResourcesBeforeResponding(): void
    {
        $lastTick = new DateTimeImmutable('-2 hours');
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 5, 1, 1, 1, 'Gaia', 12000, -20, 40, 4000, 2000, 1000, 0, 0, 0, 0, 0, 200000, 200000, 200000, 5000, $lastTick),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['metal_mine' => 3, 'solar_plant' => 3],
        ]);

        $buildQueue = $this->createMock(ProcessBuildQueue::class);
        $buildQueue->expects($this->once())->method('process')->with(1);

        $researchQueue = $this->createMock(ProcessResearchQueue::class);
        $researchQueue->expects($this->once())->method('process')->with(1);

        $shipQueue = $this->createMock(ProcessShipBuildQueue::class);
        $shipQueue->expects($this->once())->method('process')->with(1);

        $buildingConfig = [
            'metal_mine' => [
                'affects' => 'metal',
                'prod_base' => 100,
                'prod_growth' => 1.15,
                'energy_use_base' => 10,
                'energy_use_growth' => 1.1,
                'energy_use_linear' => true,
            ],
            'solar_plant' => [
                'affects' => 'energy',
                'prod_base' => 100,
                'prod_growth' => 1.12,
                'energy_use_base' => 0,
                'energy_use_growth' => 1.0,
            ],
        ];

        $resourceTick = new ResourceTickService(ResourceEffectFactory::fromBuildingConfig($this->makeBuildingConfigs($buildingConfig)));

        $sessionStorage = ['user_id' => 5];
        $session = new Session($sessionStorage);
        $flashBag = new FlashBag($session);
        $csrf = new CsrfTokenManager($session);
        $renderer = new ViewRenderer(__DIR__ . '/../../templates');

        $controller = new ResourceApiController(
            $planetRepository,
            $buildQueue,
            $researchQueue,
            $shipQueue,
            $buildingStates,
            $resourceTick,
            $renderer,
            $session,
            $flashBag,
            $csrf,
            'http://localhost'
        );

        $request = new Request('GET', '/api/resources', ['planet' => 1], [], $session);

        $response = $controller->show($request);
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame(1, $payload['planetId']);
        self::assertGreaterThan(4000, $payload['resources']['metal']['value']);
        self::assertGreaterThan(0, $payload['resources']['metal']['perHour']);

        $updatedPlanet = $planetRepository->find(1);
        self::assertNotNull($updatedPlanet);
        self::assertGreaterThan($lastTick->getTimestamp(), $updatedPlanet->getLastResourceTick()->getTimestamp());
        self::assertGreaterThan(0, $updatedPlanet->getMetalPerHour());
    }

    public function testResourceApiRealignsFutureTickAndAllowsProgression(): void
    {
        $futureTick = new DateTimeImmutable('+1 hour');
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 5, 1, 1, 1, 'Gaia', 12000, -20, 40, 0, 0, 0, 0, 0, 0, 0, 0, 500000, 500000, 500000, 500000, $futureTick),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['metal_mine' => 1, 'solar_plant' => 1],
        ]);

        $buildQueue = $this->createMock(ProcessBuildQueue::class);
        $buildQueue->expects($this->exactly(2))->method('process')->with(1);

        $researchQueue = $this->createMock(ProcessResearchQueue::class);
        $researchQueue->expects($this->exactly(2))->method('process')->with(1);

        $shipQueue = $this->createMock(ProcessShipBuildQueue::class);
        $shipQueue->expects($this->exactly(2))->method('process')->with(1);

        $buildingConfig = [
            'metal_mine' => [
                'affects' => 'metal',
                'prod_base' => 7200,
                'prod_growth' => 1.0,
                'energy_use_base' => 0,
                'energy_use_growth' => 1.0,
            ],
            'solar_plant' => [
                'affects' => 'energy',
                'prod_base' => 7200,
                'prod_growth' => 1.0,
                'energy_use_base' => 0,
                'energy_use_growth' => 1.0,
            ],
        ];

        $resourceTick = new ResourceTickService(ResourceEffectFactory::fromBuildingConfig($this->makeBuildingConfigs($buildingConfig)));

        $sessionStorage = ['user_id' => 5];
        $session = new Session($sessionStorage);
        $flashBag = new FlashBag($session);
        $csrf = new CsrfTokenManager($session);
        $renderer = new ViewRenderer(__DIR__ . '/../../templates');

        $controller = new ResourceApiController(
            $planetRepository,
            $buildQueue,
            $researchQueue,
            $shipQueue,
            $buildingStates,
            $resourceTick,
            $renderer,
            $session,
            $flashBag,
            $csrf,
            'http://localhost'
        );

        $request = new Request('GET', '/api/resources', ['planet' => 1], [], $session);

        $firstResponse = $controller->show($request);
        $firstPayload = json_decode($firstResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($firstPayload['success']);
        $firstPlanet = $planetRepository->find(1);
        self::assertNotNull($firstPlanet);
        self::assertLessThan($futureTick->getTimestamp(), $firstPlanet->getLastResourceTick()->getTimestamp());

        $initialMetal = $firstPayload['resources']['metal']['value'];

        usleep(1_200_000);

        $secondResponse = $controller->show($request);
        $secondPayload = json_decode($secondResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($secondPayload['success']);
        self::assertGreaterThan($initialMetal, $secondPayload['resources']['metal']['value']);
    }

    public function testFleetLaunchAndReturnLifecycle(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 7, 1, 20, 7, 'Helios', 12000, -20, 40, 10000, 6000, 4000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable()),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['shipyard' => 2],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $shipQueue = new InMemoryShipBuildQueueRepository();
        $fleetRepository = new InMemoryFleetRepository();
        $buildingCatalog = new BuildingCatalog($this->makeBuildingConfigs([
            'shipyard' => [
                'label' => 'Chantier',
                'base_cost' => ['metal' => 100],
                'growth_cost' => 1.0,
                'base_time' => 1,
                'growth_time' => 1.0,
                'prod_base' => 0,
                'prod_growth' => 1.0,
                'energy_use_base' => 0,
                'energy_use_growth' => 1.0,
                'energy_use_linear' => false,
                'affects' => 'energy',
                'ship_build_speed_bonus' => ['base' => 0.1, 'linear' => true, 'max' => 0.9],
            ],
        ]));
        $buildingCalculator = new BuildingCalculator();
        $catalog = new ShipCatalog($this->makeShipConfigs([
            'fighter' => [
                'label' => 'Chasseur',
                'category' => 'Escadre',
                'role' => 'Intercepteur',
                'description' => '',
                'base_cost' => ['metal' => 200, 'crystal' => 100, 'hydrogen' => 50],
                'build_time' => 4,
                'stats' => ['vitesse' => 12000],
                'requires_research' => [],
                'image' => '',
            ],
        ]));

        $playerStats = new InMemoryPlayerStatsRepository();
        $buildShips = new BuildShips($planetRepository, $buildingStates, $researchStates, $shipQueue, $playerStats, $buildingCatalog, $buildingCalculator, $catalog);
        $shipProcessor = new ProcessShipBuildQueue($shipQueue, $fleetRepository);

        $result = $buildShips->execute(1, 7, 'fighter', 4);
        self::assertTrue($result['success']);
        $shipQueue->forceComplete(1);
        $shipProcessor->process(1);
        self::assertSame(['fighter' => 4], $fleetRepository->getFleet(1));

        $navigation = new FleetNavigationService();
        $departure = new DateTimeImmutable('2025-09-21 09:00:00');
        $origin = ['galaxy' => 1, 'system' => 20, 'position' => 7];
        $destination = ['galaxy' => 1, 'system' => 21, 'position' => 10];
        $composition = ['fighter' => 3];
        $shipStats = [
            'fighter' => ['speed' => 12000, 'fuel_per_distance' => 0.4],
        ];

        $plan = $navigation->plan($origin, $destination, $composition, $shipStats, $departure);

        self::assertSame(120, $plan['distance']);
        self::assertSame(12000, $plan['speed']);
        self::assertSame(36, $plan['travel_time']);
        self::assertSame(144, $plan['fuel']);
    }
}

/**
 * @implements PlanetRepositoryInterface
 */
class InMemoryPlanetRepository implements PlanetRepositoryInterface
{
    /** @var array<int, Planet> */
    private array $planets;

    public function __construct(array $planets)
    {
        $this->planets = $planets;
    }

    public function findByUser(int $userId): array
    {
        return array_values(array_filter(
            $this->planets,
            static fn (Planet $planet): bool => $planet->getUserId() === $userId
        ));
    }

    public function find(int $id): ?Planet
    {
        return $this->planets[$id] ?? null;
    }

    public function findByCoordinates(int $galaxy, int $system): array
    {
        return array_values(array_filter(
            $this->planets,
            static fn (Planet $planet): bool => $planet->getGalaxy() === $galaxy && $planet->getSystem() === $system
        ));
    }

    public function createHomeworld(int $userId): Planet
    {
        throw new \RuntimeException('Not implemented');
    }

    public function update(Planet $planet): void
    {
        $this->planets[$planet->getId()] = $planet;
    }

    public function rename(int $planetId, string $name): void
    {
        if (!isset($this->planets[$planetId])) {
            return;
        }

        $this->planets[$planetId]->rename($name);
    }
}

/**
 * @implements BuildingStateRepositoryInterface
 */
class InMemoryBuildingStateRepository implements BuildingStateRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $levels;

    public function __construct(array $initial = [])
    {
        $this->levels = $initial;
    }

    public function getLevels(int $planetId): array
    {
        return $this->levels[$planetId] ?? [];
    }

    public function setLevel(int $planetId, string $buildingKey, int $level): void
    {
        $this->levels[$planetId][$buildingKey] = $level;
    }
}

/**
 * @implements BuildQueueRepositoryInterface
 */
class InMemoryBuildQueueRepository implements BuildQueueRepositoryInterface
{
    /** @var array<int, array{planet_id: int, building: string, target: int, ends_at: \DateTimeImmutable}> */
    private array $jobs = [];

    private int $nextId = 1;

    public function countActive(int $planetId): int
    {
        return count($this->getActiveQueue($planetId));
    }

    public function getActiveQueue(int $planetId): array
    {
        $now = new \DateTimeImmutable();
        $active = array_filter(
            $this->jobs,
            static fn (array $job): bool => $job['planet_id'] === $planetId && $job['ends_at'] > $now
        );

        return array_map(
            fn (array $job): \App\Domain\Entity\BuildJob => new \App\Domain\Entity\BuildJob(
                $job['id'],
                $job['planet_id'],
                $job['building'],
                $job['target'],
                $job['ends_at']
            ),
            array_values($active)
        );
    }

    public function enqueue(int $planetId, string $buildingKey, int $targetLevel, int $durationSeconds): void
    {
        $this->jobs[] = [
            'id' => $this->nextId++,
            'planet_id' => $planetId,
            'building' => $buildingKey,
            'target' => $targetLevel,
            'ends_at' => (new \DateTimeImmutable())->modify('+' . $durationSeconds . ' seconds'),
        ];
    }

    public function finalizeDueJobs(int $planetId): array
    {
        $now = new \DateTimeImmutable();
        $due = [];
        $remaining = [];

        foreach ($this->jobs as $job) {
            if ($job['planet_id'] === $planetId && $job['ends_at'] <= $now) {
                $due[] = $job;
            } else {
                $remaining[] = $job;
            }
        }

        $this->jobs = $remaining;

        return array_map(
            fn (array $job): \App\Domain\Entity\BuildJob => new \App\Domain\Entity\BuildJob(
                $job['id'],
                $job['planet_id'],
                $job['building'],
                $job['target'],
                $job['ends_at']
            ),
            $due
        );
    }

    public function forceComplete(int $planetId): void
    {
        foreach ($this->jobs as &$job) {
            if ($job['planet_id'] === $planetId) {
                $job['ends_at'] = new \DateTimeImmutable('-1 second');
            }
        }
    }

    public function forceCompleteNext(int $planetId): void
    {
        foreach ($this->jobs as &$job) {
            if ($job['planet_id'] === $planetId) {
                $job['ends_at'] = new \DateTimeImmutable('-1 second');
                break;
            }
        }
    }
}

/**
 * @implements ResearchStateRepositoryInterface
 */
class InMemoryResearchStateRepository implements ResearchStateRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $levels;

    public function __construct(array $initial = [])
    {
        $this->levels = $initial;
    }

    public function getLevels(int $planetId): array
    {
        return $this->levels[$planetId] ?? [];
    }

    public function setLevel(int $planetId, string $researchKey, int $level): void
    {
        $this->levels[$planetId][$researchKey] = $level;
    }
}

/**
 * @implements ResearchQueueRepositoryInterface
 */
class InMemoryResearchQueueRepository implements ResearchQueueRepositoryInterface
{
    /** @var array<int, array{planet_id: int, research: string, target: int, ends_at: \DateTimeImmutable}> */
    private array $jobs = [];
    private int $nextId = 1;
    private ?int $lastDuration = null;

    public function countActive(int $planetId): int
    {
        return count($this->getActiveQueue($planetId));
    }

    public function getActiveQueue(int $planetId): array
    {
        $now = new \DateTimeImmutable();
        $active = array_filter(
            $this->jobs,
            static fn (array $job): bool => $job['planet_id'] === $planetId && $job['ends_at'] > $now
        );

        return array_map(
            fn (array $job): \App\Domain\Entity\ResearchJob => new \App\Domain\Entity\ResearchJob(
                $job['id'],
                $job['planet_id'],
                $job['research'],
                $job['target'],
                $job['ends_at']
            ),
            array_values($active)
        );
    }

    public function enqueue(int $planetId, string $researchKey, int $targetLevel, int $durationSeconds): void
    {
        $this->jobs[] = [
            'id' => $this->nextId++,
            'planet_id' => $planetId,
            'research' => $researchKey,
            'target' => $targetLevel,
            'ends_at' => (new \DateTimeImmutable())->modify('+' . $durationSeconds . ' seconds'),
        ];
        $this->lastDuration = $durationSeconds;
    }

    public function getLastDuration(): ?int
    {
        return $this->lastDuration;
    }

    public function finalizeDueJobs(int $planetId): array
    {
        $now = new \DateTimeImmutable();
        $due = [];
        $remaining = [];

        foreach ($this->jobs as $job) {
            if ($job['planet_id'] === $planetId && $job['ends_at'] <= $now) {
                $due[] = $job;
            } else {
                $remaining[] = $job;
            }
        }

        $this->jobs = $remaining;

        return array_map(
            fn (array $job): \App\Domain\Entity\ResearchJob => new \App\Domain\Entity\ResearchJob(
                $job['id'],
                $job['planet_id'],
                $job['research'],
                $job['target'],
                $job['ends_at']
            ),
            $due
        );
    }

    public function forceComplete(int $planetId): void
    {
        foreach ($this->jobs as &$job) {
            if ($job['planet_id'] === $planetId) {
                $job['ends_at'] = new \DateTimeImmutable('-1 second');
            }
        }
    }
}

/**
 * @implements ShipBuildQueueRepositoryInterface
 */
class InMemoryShipBuildQueueRepository implements ShipBuildQueueRepositoryInterface
{
    /** @var array<int, array{planet_id: int, ship: string, quantity: int, ends_at: \DateTimeImmutable, duration: int}> */
    private array $jobs = [];
    private int $nextId = 1;

    public function countActive(int $planetId): int
    {
        return count($this->getActiveQueue($planetId));
    }

    public function getActiveQueue(int $planetId): array
    {
        $now = new \DateTimeImmutable();
        $active = array_filter(
            $this->jobs,
            static fn (array $job): bool => $job['planet_id'] === $planetId && $job['ends_at'] > $now
        );

        return array_map(
            fn (array $job): \App\Domain\Entity\ShipBuildJob => new \App\Domain\Entity\ShipBuildJob(
                $job['id'],
                $job['planet_id'],
                $job['ship'],
                $job['quantity'],
                $job['ends_at']
            ),
            array_values($active)
        );
    }

    public function enqueue(int $planetId, string $shipKey, int $quantity, int $durationSeconds): void
    {
        $this->jobs[] = [
            'id' => $this->nextId++,
            'planet_id' => $planetId,
            'ship' => $shipKey,
            'quantity' => $quantity,
            'ends_at' => (new \DateTimeImmutable())->modify('+' . $durationSeconds . ' seconds'),
            'duration' => $durationSeconds,
        ];
    }

    public function getLastDuration(int $planetId): ?int
    {
        for ($i = count($this->jobs) - 1; $i >= 0; $i--) {
            $job = $this->jobs[$i];
            if ($job['planet_id'] === $planetId) {
                return $job['duration'] ?? null;
            }
        }

        return null;
    }

    public function finalizeDueJobs(int $planetId): array
    {
        $now = new \DateTimeImmutable();
        $due = [];
        $remaining = [];

        foreach ($this->jobs as $job) {
            if ($job['planet_id'] === $planetId && $job['ends_at'] <= $now) {
                $due[] = $job;
            } else {
                $remaining[] = $job;
            }
        }

        $this->jobs = $remaining;

        return array_map(
            fn (array $job): \App\Domain\Entity\ShipBuildJob => new \App\Domain\Entity\ShipBuildJob(
                $job['id'],
                $job['planet_id'],
                $job['ship'],
                $job['quantity'],
                $job['ends_at']
            ),
            $due
        );
    }

    public function forceComplete(int $planetId): void
    {
        foreach ($this->jobs as &$job) {
            if ($job['planet_id'] === $planetId) {
                $job['ends_at'] = new \DateTimeImmutable('-1 second');
            }
        }
    }
}

/**
 * @implements PlayerStatsRepositoryInterface
 */
class InMemoryPlayerStatsRepository implements PlayerStatsRepositoryInterface
{
    private int $buildingSpent = 0;

    private int $scienceSpent = 0;

    private int $fleetSpent = 0;

    public function addBuildingSpending(int $playerId, int $amount): void
    {
        if ($amount > 0) {
            $this->buildingSpent += $amount;
        }
    }

    public function addScienceSpending(int $playerId, int $amount): void
    {
        if ($amount > 0) {
            $this->scienceSpent += $amount;
        }
    }

    public function addFleetSpending(int $playerId, int $amount): void
    {
        if ($amount > 0) {
            $this->fleetSpent += $amount;
        }
    }

    public function getBuildingSpending(int $playerId): int
    {
        return $this->buildingSpent;
    }

    public function getScienceSpending(int $playerId): int
    {
        return $this->scienceSpent;
    }

    public function getFleetSpending(int $playerId): int
    {
        return $this->fleetSpent;
    }
}

/**
 * @implements FleetRepositoryInterface
 */
class InMemoryFleetRepository implements FleetRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $fleets = [];

    public function getFleet(int $planetId): array
    {
        return $this->fleets[$planetId] ?? [];
    }

    public function addShips(int $planetId, string $key, int $quantity): void
    {
        $this->fleets[$planetId][$key] = ($this->fleets[$planetId][$key] ?? 0) + $quantity;
    }
}
