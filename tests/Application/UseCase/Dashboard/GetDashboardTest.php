<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Dashboard;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Dashboard\GetDashboard;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\PlayerStatsRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\EconomySettings;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ShipCatalog;
use PHPUnit\Framework\TestCase;

class GetDashboardTest extends TestCase
{
    public function testSciencePowerDerivesFromScienceSpending(): void
    {
        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->expects(self::once())
            ->method('findByUser')
            ->with(123)
            ->willReturn([]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildQueue = $this->createMock(BuildQueueRepositoryInterface::class);
        $researchQueue = $this->createMock(ResearchQueueRepositoryInterface::class);
        $shipQueue = $this->createMock(ShipBuildQueueRepositoryInterface::class);
        $researchStates = $this->createMock(ResearchStateRepositoryInterface::class);
        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);

        $playerStats = $this->createMock(PlayerStatsRepositoryInterface::class);
        $playerStats->expects(self::once())
            ->method('getScienceSpending')
            ->with(123)
            ->willReturn(4200);

        $processBuildQueue = $this->createMock(ProcessBuildQueue::class);
        $processBuildQueue->expects(self::never())->method('process');

        $processResearchQueue = $this->createMock(ProcessResearchQueue::class);
        $processResearchQueue->expects(self::never())->method('process');

        $processShipQueue = $this->createMock(ProcessShipBuildQueue::class);
        $processShipQueue->expects(self::never())->method('process');

        $useCase = new GetDashboard(
            $planetRepository,
            $buildingStates,
            $buildQueue,
            $researchQueue,
            $shipQueue,
            $playerStats,
            $researchStates,
            $fleetRepository,
            new BuildingCatalog([]),
            new ResearchCatalog([]),
            new ShipCatalog([]),
            new BuildingCalculator(new EconomySettings()),
            $processBuildQueue,
            $processResearchQueue,
            $processShipQueue
        );

        $result = $useCase->execute(123);

        self::assertSame(4200, $result['empire']['scienceSpent']);
        self::assertSame(4, $result['empire']['sciencePower']);
        self::assertSame(0, $result['empire']['planetCount']);
    }
}
