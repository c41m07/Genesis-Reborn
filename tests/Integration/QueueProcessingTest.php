<?php

namespace App\Tests\Integration;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Building\UpgradeBuilding;
use App\Application\UseCase\Research\StartResearch;
use App\Application\UseCase\Shipyard\BuildShips;
use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\FleetNavigationService;
use App\Domain\Service\FleetResolutionService;
use App\Domain\Service\ResearchCalculator;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ShipCatalog;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class QueueProcessingTest extends TestCase
{
    public function testBuildingUpgradeIsQueuedAndProcessed(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 42, 1, 1, 1, 'Gaia', 5000, 5000, 5000, 0, 0, 0, 0, 0),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['metal_mine' => 0, 'research_lab' => 2, 'shipyard' => 1],
        ]);
        $buildQueue = new InMemoryBuildQueueRepository();
        $catalog = new BuildingCatalog([
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
        ]);
        $calculator = new BuildingCalculator();

        $useCase = new UpgradeBuilding($planetRepository, $buildingStates, $buildQueue, $catalog, $calculator);
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
    }

    public function testResearchStartIsQueuedAndProcessed(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 42, 1, 1, 1, 'Gaia', 5000, 5000, 5000, 0, 0, 0, 0, 0),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['research_lab' => 3],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => ['energy_tech' => 0],
        ]);
        $researchQueue = new InMemoryResearchQueueRepository();
        $catalog = new ResearchCatalog([
            'energy_tech' => [
                'label' => 'Technologie énergétique',
                'category' => 'Sciences',
                'description' => '',
                'base_cost' => ['metal' => 100],
                'base_time' => 5,
                'growth_cost' => 1.5,
                'growth_time' => 2.0,
                'max_level' => 5,
                'requires' => [],
                'requires_lab' => 1,
                'image' => '',
            ],
        ]);
        $calculator = new ResearchCalculator();

        $useCase = new StartResearch($planetRepository, $buildingStates, $researchStates, $researchQueue, $catalog, $calculator);
        $processor = new ProcessResearchQueue($researchQueue, $researchStates);

        $result = $useCase->execute(1, 42, 'energy_tech');
        self::assertTrue($result['success']);
        self::assertSame(0, $researchStates->getLevels(1)['energy_tech']);
        self::assertSame(1, $researchQueue->countActive(1));

        $researchQueue->forceComplete(1);
        $processor->process(1);

        self::assertSame(1, $researchStates->getLevels(1)['energy_tech']);
        self::assertSame(0, $researchQueue->countActive(1));
    }

    public function testShipProductionIsQueuedAndProcessed(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 42, 1, 1, 1, 'Gaia', 5000, 5000, 5000, 0, 0, 0, 0, 0),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['shipyard' => 2],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $shipQueue = new InMemoryShipBuildQueueRepository();
        $fleetRepository = new InMemoryFleetRepository();
        $catalog = new ShipCatalog([
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
        ]);

        $useCase = new BuildShips($planetRepository, $buildingStates, $researchStates, $shipQueue, $catalog);
        $processor = new ProcessShipBuildQueue($shipQueue, $fleetRepository);

        $result = $useCase->execute(1, 42, 'fighter', 3);
        self::assertTrue($result['success']);
        self::assertSame([], $fleetRepository->getFleet(1));
        self::assertSame(1, $shipQueue->countActive(1));

        $shipQueue->forceComplete(1);
        $processor->process(1);

        self::assertSame(['fighter' => 3], $fleetRepository->getFleet(1));
        self::assertSame(0, $shipQueue->countActive(1));
    }

    public function testFleetLaunchAndReturnLifecycle(): void
    {
        $planetRepository = new InMemoryPlanetRepository([
            1 => new Planet(1, 7, 1, 20, 7, 'Helios', 10000, 6000, 4000, 0, 0, 0, 0, 0),
        ]);
        $buildingStates = new InMemoryBuildingStateRepository([
            1 => ['shipyard' => 2],
        ]);
        $researchStates = new InMemoryResearchStateRepository([
            1 => [],
        ]);
        $shipQueue = new InMemoryShipBuildQueueRepository();
        $fleetRepository = new InMemoryFleetRepository();
        $catalog = new ShipCatalog([
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
        ]);

        $buildShips = new BuildShips($planetRepository, $buildingStates, $researchStates, $shipQueue, $catalog);
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

        $mission = [
            'mission_type' => 'pve',
            'status' => 'outbound',
            'arrival_at' => $plan['arrival_time'],
            'return_at' => null,
            'travel_time_seconds' => $plan['travel_time'],
            'mission_payload' => [
                'mission' => 'derelict_station',
                'composition' => $composition,
                'origin_planet' => 1,
                'fuel_used' => $plan['fuel'],
            ],
        ];

        $resolution = new FleetResolutionService();
        $afterArrival = $plan['arrival_time']->add(new DateInterval('PT1S'));
        $stateAfterArrival = $resolution->advance([$mission], $afterArrival);

        $fleetAfterArrival = $stateAfterArrival[0];
        self::assertSame('returning', $fleetAfterArrival['status']);
        self::assertEquals($afterArrival, $fleetAfterArrival['arrival_at']);
        $expectedReturnAt = $afterArrival->add(new DateInterval('PT' . $plan['travel_time'] . 'S'));
        self::assertEquals($expectedReturnAt, $fleetAfterArrival['return_at']);
        self::assertSame('victory', $fleetAfterArrival['mission_payload']['last_resolution']['result']);

        $afterReturn = $expectedReturnAt->add(new DateInterval('PT5S'));
        $stateAfterReturn = $resolution->advance($stateAfterArrival, $afterReturn);
        $fleetAfterReturn = $stateAfterReturn[0];

        self::assertSame('completed', $fleetAfterReturn['status']);
        self::assertEquals($afterReturn, $fleetAfterReturn['return_at']);
        self::assertSame('victory', $fleetAfterReturn['mission_payload']['last_resolution']['result']);
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

    public function countActive(int $planetId): int
    {
        return count($this->getActiveQueue($planetId));
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

    public function countActive(int $planetId): int
    {
        return count($this->getActiveQueue($planetId));
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
    /** @var array<int, array{planet_id: int, ship: string, quantity: int, ends_at: \DateTimeImmutable}> */
    private array $jobs = [];
    private int $nextId = 1;

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

    public function countActive(int $planetId): int
    {
        return count($this->getActiveQueue($planetId));
    }

    public function enqueue(int $planetId, string $shipKey, int $quantity, int $durationSeconds): void
    {
        $this->jobs[] = [
            'id' => $this->nextId++,
            'planet_id' => $planetId,
            'ship' => $shipKey,
            'quantity' => $quantity,
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
