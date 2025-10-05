<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Resource;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Fleet\ProcessFleetArrivals;
use App\Application\UseCase\Fleet\ProcessFleetReturns;
use App\Application\UseCase\Resource\GetResourceSnapshot;
use App\Domain\Entity\Planet;
use App\Domain\Entity\FleetMovement;
use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\ValueObject\Coordinates;
use App\Domain\Service\ResourceTickService;
use BadMethodCallException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetResourceSnapshotTest extends TestCase
{
    public function testRejectsInvalidPlanetId(): void
    {
        [$processArrivals, $processReturns, $movementRepository] = $this->createFleetProcessors();

        $useCase = new GetResourceSnapshot(
            $this->createPlanetRepository(null),
            $this->createBuildProcessorStub(),
            $this->createResearchProcessorStub(),
            $this->createShipProcessorStub(),
            $processArrivals,
            $processReturns,
            $this->createBuildingStateRepository(),
            $this->createTickServiceStub([])
        );

        $result = $useCase->execute(1, 0);

        self::assertSame(400, $result->getStatusCode());
        self::assertFalse($result->getPayload()['success']);
        self::assertNull($result->getPlanet());
        self::assertSame(0, $movementRepository->arrivalsCalls);
        self::assertSame(0, $movementRepository->returnsCalls);
    }

    public function testReturnsSnapshotForOwnedPlanet(): void
    {
        $planet = $this->createPlanet();
        $planetRepository = $this->createPlanetRepository($planet);
        [$processArrivals, $processReturns, $movementRepository] = $this->createFleetProcessors();
        $tickResult = [
            $planet->getId() => [
                'elapsed_seconds' => 60,
                'resources' => [
                    'metal' => 1500,
                    'crystal' => 750,
                    'hydrogen' => 300,
                    'energy' => 120,
                ],
                'production_per_hour' => [
                    'metal' => 3600,
                    'crystal' => 1800,
                    'hydrogen' => 900,
                    'energy' => 600,
                ],
                'capacities' => [
                    'metal' => 20000,
                    'crystal' => 20000,
                    'hydrogen' => 20000,
                    'energy' => 20000,
                ],
            ],
        ];

        $useCase = new GetResourceSnapshot(
            $planetRepository,
            $this->createBuildProcessorStub(),
            $this->createResearchProcessorStub(),
            $this->createShipProcessorStub(),
            $processArrivals,
            $processReturns,
            $this->createBuildingStateRepository(['mine' => 1]),
            $this->createTickServiceStub($tickResult)
        );

        $result = $useCase->execute($planet->getUserId(), $planet->getId());

        self::assertSame(200, $result->getStatusCode());
        self::assertTrue($result->getPayload()['success']);
        self::assertInstanceOf(Planet::class, $result->getPlanet());
        self::assertSame(1500, $result->getPlanet()->getMetal());
        self::assertSame(3600, $result->getPlanet()->getMetalPerHour());
        self::assertSame(20000, $result->getPlanet()->getMetalCapacity());
        self::assertSame($planet->getId(), $result->getPayload()['planetId']);
        self::assertCount(1, $planetRepository->updates);
        self::assertSame($planet->getId(), $planetRepository->updates[0]->getId());
        self::assertSame(1, $movementRepository->arrivalsCalls);
        self::assertSame(1, $movementRepository->returnsCalls);
    }

    /**
     * @param array<string, int> $levels
     */
    private function createBuildingStateRepository(array $levels = []): BuildingStateRepositoryInterface
    {
        return new class ($levels) implements BuildingStateRepositoryInterface {
            public function __construct(private array $levels)
            {
            }

            public function getLevels(int $planetId): array
            {
                return $this->levels;
            }

            public function setLevel(int $planetId, string $buildingKey, int $level): void
            {
            }
        };
    }

    private function createPlanetRepository(?Planet $planet): PlanetRepositoryInterface
    {
        return new class ($planet) implements PlanetRepositoryInterface {
            public array $updates = [];

            public function __construct(private ?Planet $planet)
            {
            }

            public function findByUser(int $userId): array
            {
                return $this->planet ? [$this->planet] : [];
            }

            public function find(int $id): ?Planet
            {
                if (!$this->planet) {
                    return null;
                }

                return $this->planet->getId() === $id ? $this->planet : null;
            }

            public function findByCoordinates(int $galaxy, int $system): array
            {
                return [];
            }

            public function createHomeworld(int $userId): Planet
            {
                throw new \RuntimeException('Not implemented.');
            }

            public function update(Planet $planet): void
            {
                $this->updates[] = clone $planet;
            }

            public function rename(int $planetId, string $name): void
            {
            }
        };
    }

    private function createBuildProcessorStub(): ProcessBuildQueue
    {
        return new class () extends ProcessBuildQueue {
            public function __construct()
            {
            }

            public function process(int $planetId): void
            {
            }
        };
    }

    private function createResearchProcessorStub(): ProcessResearchQueue
    {
        return new class () extends ProcessResearchQueue {
            public function __construct()
            {
            }

            public function process(int $planetId): void
            {
            }
        };
    }

    private function createShipProcessorStub(): ProcessShipBuildQueue
    {
        return new class () extends ProcessShipBuildQueue {
            public function __construct()
            {
            }

            public function process(int $planetId): void
            {
            }
        };
    }

    /**
     * @param array<int, array<string, mixed>> $result
     */
    private function createTickServiceStub(array $result): ResourceTickService
    {
        return new class ($result) extends ResourceTickService {
            public function __construct(private array $result)
            {
            }

            public function tick(array $planetStates, \DateTimeInterface $now, ?array $effectsOverride = null): array
            {
                return $this->result;
            }
        };
    }

    /**
     * @return array{0: ProcessFleetArrivals, 1: ProcessFleetReturns, 2: object{arrivalsCalls:int, returnsCalls:int}}
     */
    private function createFleetProcessors(): array
    {
        $repository = new class () implements FleetMovementRepositoryInterface {
            public int $arrivalsCalls = 0;
            public int $returnsCalls = 0;

            public function launchMission(
                int $playerId,
                int $originPlanetId,
                ?int $fleetId,
                ?int $destinationPlanetId,
                Coordinates $destinationCoordinates,
                FleetMission $mission,
                FleetStatus $status,
                array $composition,
                int $fuelConsumed,
                DateTimeImmutable $departureAt,
                DateTimeImmutable $arrivalAt,
                int $travelTimeSeconds,
                array $payload = []
            ): FleetMovement {
                throw new BadMethodCallException('Not implemented.');
            }

            public function findActiveByOriginPlanet(int $planetId): array
            {
                return [];
            }

            public function findActiveByPlayer(int $playerId): array
            {
                return [];
            }

            public function findArrivedMissions(DateTimeImmutable $now, ?int $playerId = null): array
            {
                $this->arrivalsCalls++;

                return [];
            }

            public function completeArrival(FleetMovement $movement, DateTimeImmutable $processedAt): void
            {
            }

            public function findReturningMissions(DateTimeImmutable $now, ?int $playerId = null): array
            {
                $this->returnsCalls++;

                return [];
            }

            public function completeReturn(FleetMovement $movement, DateTimeImmutable $processedAt): void
            {
            }
        };

        return [
            new ProcessFleetArrivals($repository),
            new ProcessFleetReturns($repository),
            $repository,
        ];
    }

    private function createPlanet(): Planet
    {
        return new Planet(
            9,
            3,
            1,
            1,
            1,
            'Atlas',
            5000,
            -5,
            25,
            1200,
            600,
            300,
            150,
            2400,
            1600,
            900,
            500,
            15000,
            15000,
            15000,
            15000,
            new DateTimeImmutable('-30 minutes')
        );
    }
}
