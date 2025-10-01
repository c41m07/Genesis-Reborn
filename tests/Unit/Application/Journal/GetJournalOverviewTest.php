<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Journal;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Journal\GetJournalOverview;
use App\Domain\Entity\BuildJob;
use App\Domain\Entity\Planet;
use App\Domain\Entity\ResearchJob;
use App\Domain\Entity\ShipBuildJob;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ShipCatalog;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetJournalOverviewTest extends TestCase
{
    public function testReturnsMessageWhenNoPlanets(): void
    {
        $useCase = new GetJournalOverview(
            new class () implements PlanetRepositoryInterface {
                public function findByUser(int $userId): array
                {
                    return [];
                }

                public function find(int $id): ?Planet
                {
                    return null;
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
                }

                public function rename(int $planetId, string $name): void
                {
                }
            },
            new class () implements BuildingStateRepositoryInterface {
                public function getLevels(int $planetId): array
                {
                    return [];
                }

                public function setLevel(int $planetId, string $buildingKey, int $level): void
                {
                }
            },
            new class () implements BuildQueueRepositoryInterface {
                public function getActiveQueue(int $planetId): array
                {
                    return [];
                }

                public function countActive(int $planetId): int
                {
                    return 0;
                }

                public function enqueue(int $planetId, string $buildingKey, int $targetLevel, int $durationSeconds): void
                {
                }

                public function finalizeDueJobs(int $planetId): array
                {
                    return [];
                }
            },
            new class () implements ResearchQueueRepositoryInterface {
                public function getActiveQueue(int $planetId): array
                {
                    return [];
                }

                public function countActive(int $planetId): int
                {
                    return 0;
                }

                public function enqueue(int $planetId, string $researchKey, int $targetLevel, int $durationSeconds): void
                {
                }

                public function finalizeDueJobs(int $planetId): array
                {
                    return [];
                }
            },
            new class () implements ShipBuildQueueRepositoryInterface {
                public function getActiveQueue(int $planetId): array
                {
                    return [];
                }

                public function countActive(int $planetId): int
                {
                    return 0;
                }

                public function enqueue(int $planetId, string $shipKey, int $quantity, int $durationSeconds): void
                {
                }

                public function finalizeDueJobs(int $planetId): array
                {
                    return [];
                }
            },
            new class () extends ProcessBuildQueue {
                public array $calls = [];

                public function __construct()
                {
                }

                public function process(int $planetId): void
                {
                    $this->calls[] = $planetId;
                }
            },
            new class () extends ProcessResearchQueue {
                public array $calls = [];

                public function __construct()
                {
                }

                public function process(int $planetId): void
                {
                    $this->calls[] = $planetId;
                }
            },
            new class () extends ProcessShipBuildQueue {
                public array $calls = [];

                public function __construct()
                {
                }

                public function process(int $planetId): void
                {
                    $this->calls[] = $planetId;
                }
            },
            $this->createBuildingCatalogStub(),
            $this->createResearchCatalogStub(),
            $this->createShipCatalogStub()
        );

        $result = $useCase->execute(7, []);

        self::assertSame([], $result['planets']);
        self::assertNotEmpty($result['messages']);
        self::assertSame('info', $result['messages'][0]['type']);
    }

    public function testAggregatesQueuesAndEvents(): void
    {
        $planet = $this->createPlanet(5);
        $buildJob = new BuildJob(1, 5, 'solar_plant', 3, new DateTimeImmutable('+10 minutes'));
        $researchJob = new ResearchJob(2, 5, 'energy_technology', 2, new DateTimeImmutable('+20 minutes'));
        $shipJob = new ShipBuildJob(3, 5, 'fighter', 4, new DateTimeImmutable('+30 minutes'));

        $buildProcessor = new class () extends ProcessBuildQueue {
            public array $calls = [];

            public function __construct()
            {
            }

            public function process(int $planetId): void
            {
                $this->calls[] = $planetId;
            }
        };

        $researchProcessor = new class () extends ProcessResearchQueue {
            public array $calls = [];

            public function __construct()
            {
            }

            public function process(int $planetId): void
            {
                $this->calls[] = $planetId;
            }
        };

        $shipProcessor = new class () extends ProcessShipBuildQueue {
            public array $calls = [];

            public function __construct()
            {
            }

            public function process(int $planetId): void
            {
                $this->calls[] = $planetId;
            }
        };

        $useCase = new GetJournalOverview(
            new class ($planet) implements PlanetRepositoryInterface {
                public function __construct(private Planet $planet)
                {
                }

                public function findByUser(int $userId): array
                {
                    return [$this->planet];
                }

                public function find(int $id): ?Planet
                {
                    return $this->planet->getId() === $id ? $this->planet : null;
                }

                public function findByCoordinates(int $galaxy, int $system): array
                {
                    return [$this->planet];
                }

                public function createHomeworld(int $userId): Planet
                {
                    throw new \RuntimeException('Not implemented.');
                }

                public function update(Planet $planet): void
                {
                }

                public function rename(int $planetId, string $name): void
                {
                }
            },
            new class () implements BuildingStateRepositoryInterface {
                public function getLevels(int $planetId): array
                {
                    return ['research_lab' => 1, 'shipyard' => 1];
                }

                public function setLevel(int $planetId, string $buildingKey, int $level): void
                {
                }
            },
            new class ($buildJob) implements BuildQueueRepositoryInterface {
                public function __construct(private BuildJob $job)
                {
                }

                public function getActiveQueue(int $planetId): array
                {
                    return [$this->job];
                }

                public function countActive(int $planetId): int
                {
                    return 1;
                }

                public function enqueue(int $planetId, string $buildingKey, int $targetLevel, int $durationSeconds): void
                {
                }

                public function finalizeDueJobs(int $planetId): array
                {
                    return [];
                }
            },
            new class ($researchJob) implements ResearchQueueRepositoryInterface {
                public function __construct(private ResearchJob $job)
                {
                }

                public function getActiveQueue(int $planetId): array
                {
                    return [$this->job];
                }

                public function countActive(int $planetId): int
                {
                    return 1;
                }

                public function enqueue(int $planetId, string $researchKey, int $targetLevel, int $durationSeconds): void
                {
                }

                public function finalizeDueJobs(int $planetId): array
                {
                    return [];
                }
            },
            new class ($shipJob) implements ShipBuildQueueRepositoryInterface {
                public function __construct(private ShipBuildJob $job)
                {
                }

                public function getActiveQueue(int $planetId): array
                {
                    return [$this->job];
                }

                public function countActive(int $planetId): int
                {
                    return 1;
                }

                public function enqueue(int $planetId, string $shipKey, int $quantity, int $durationSeconds): void
                {
                }

                public function finalizeDueJobs(int $planetId): array
                {
                    return [];
                }
            },
            $buildProcessor,
            $researchProcessor,
            $shipProcessor,
            $this->createBuildingCatalogStub(),
            $this->createResearchCatalogStub(),
            $this->createShipCatalogStub()
        );

        $result = $useCase->execute(5, ['planet' => 5]);

        self::assertSame(5, $result['selectedPlanetId']);
        self::assertCount(3, $result['events']);
        self::assertSame(1, $result['insights']['buildQueue']);
        self::assertSame(1, $result['insights']['researchQueue']);
        self::assertSame(1, $result['insights']['shipQueue']);
        self::assertTrue($result['facilityStatuses']['research_lab']);
        self::assertTrue($result['facilityStatuses']['shipyard']);
        self::assertSame([5], $buildProcessor->calls);
        self::assertSame([5], $researchProcessor->calls);
        self::assertSame([5], $shipProcessor->calls);
    }

    private function createPlanet(int $id): Planet
    {
        return new Planet(
            $id,
            42,
            1,
            1,
            1,
            'Base',
            5000,
            -10,
            30,
            1000,
            500,
            250,
            100,
            2000,
            1500,
            1000,
            500,
            10000,
            10000,
            10000,
            10000,
            new DateTimeImmutable('-5 minutes')
        );
    }

    private function createBuildingCatalogStub(): BuildingCatalog
    {
        return new class ([]) extends BuildingCatalog {
            public function __construct(private array $definitions)
            {
            }

            public function get(string $key): \App\Domain\Entity\BuildingDefinition
            {
                return new \App\Domain\Entity\BuildingDefinition(
                    $key,
                    strtoupper($key),
                    ['metal' => 1],
                    1.0,
                    60,
                    1.0,
                    1,
                    1.0,
                    0,
                    0.0,
                    false,
                    'metal'
                );
            }

            public function all(): array
            {
                return [];
            }
        };
    }

    private function createResearchCatalogStub(): ResearchCatalog
    {
        return new class ([]) extends ResearchCatalog {
            public function __construct(private array $definitions)
            {
            }

            public function get(string $key): \App\Domain\Entity\ResearchDefinition
            {
                return new \App\Domain\Entity\ResearchDefinition(
                    $key,
                    strtoupper($key),
                    'physics',
                    'desc',
                    ['metal' => 1],
                    60,
                    1.0,
                    1.0,
                    5,
                    [],
                    1,
                    'image.png'
                );
            }
        };
    }

    private function createShipCatalogStub(): ShipCatalog
    {
        return new class ([]) extends ShipCatalog {
            public function __construct(private array $definitions)
            {
            }

            public function get(string $key): \App\Domain\Entity\ShipDefinition
            {
                return new \App\Domain\Entity\ShipDefinition(
                    $key,
                    strtoupper($key),
                    'fighters',
                    'attack',
                    'desc',
                    ['metal' => 1],
                    60,
                    ['attack' => 1],
                    [],
                    'image.png'
                );
            }
        };
    }
}
