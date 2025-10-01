<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Fleet;

use App\Application\UseCase\Fleet\PlanFleetMission;
use App\Domain\Entity\Planet;
use App\Domain\Entity\ShipDefinition;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\FleetNavigationService;
use App\Domain\Service\ShipCatalog;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlanFleetMissionTest extends TestCase
{
    public function testPlanSuccessReturnsSanitizedPayload(): void
    {
        $planet = new Planet(7, 42, 1, 2, 3, 'Gaia', 12000, -20, 30, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->expects(self::once())
            ->method('find')
            ->with(7)
            ->willReturn($planet);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->expects(self::once())
            ->method('getLevels')
            ->with(7)
            ->willReturn(['shipyard' => 2]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->expects(self::once())
            ->method('getFleet')
            ->with(7)
            ->willReturn(['fighter' => 10]);

        $definition = new ShipDefinition(
            'fighter',
            'Chasseur',
            'light',
            'Interception',
            'Unité polyvalente',
            ['metal' => 100, 'hydrogen' => 50],
            60,
            ['vitesse' => 12],
            [],
            'fighter.png'
        );

        $shipCatalog = $this->createMock(ShipCatalog::class);
        $shipCatalog->expects(self::once())
            ->method('get')
            ->with('fighter')
            ->willReturn($definition);

        $useCase = new PlanFleetMission(
            $planetRepository,
            $buildingStates,
            $fleetRepository,
            $shipCatalog,
            new FleetNavigationService()
        );

        $result = $useCase->execute(
            42,
            7,
            ['fighter' => 25, 'unknown' => 5],
            ['galaxy' => 2, 'system' => 5, 'position' => 9],
            1.0,
            'transport'
        );

        self::assertTrue($result['success']);
        self::assertSame(['fighter' => 10], $result['composition']);
        self::assertSame(['galaxy' => 2, 'system' => 5, 'position' => 9], $result['destination']);
        self::assertSame('transport', $result['mission']);
        self::assertNotNull($result['plan']);
        self::assertInstanceOf(DateTimeImmutable::class, $result['plan']['arrival_time']);
    }

    public function testPlanFailsWhenShipyardMissing(): void
    {
        $planet = new Planet(3, 99, 1, 1, 1, 'Outpost', 9000, -10, 20, 2000, 2000, 2000, 0, 0, 0, 0, 0, 50000, 50000, 50000, 500);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->method('find')->willReturn($planet);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->method('getLevels')->willReturn(['shipyard' => 0]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->method('getFleet')->willReturn(['fighter' => 5]);

        $shipCatalog = $this->createMock(ShipCatalog::class);
        $shipCatalog->expects(self::never())->method('get');

        $useCase = new PlanFleetMission(
            $planetRepository,
            $buildingStates,
            $fleetRepository,
            $shipCatalog,
            new FleetNavigationService()
        );

        $result = $useCase->execute(
            99,
            3,
            ['fighter' => 2],
            ['galaxy' => 1, 'system' => 1, 'position' => 2],
            0.8,
            'transport'
        );

        self::assertFalse($result['success']);
        self::assertNotEmpty($result['errors']);
        self::assertNull($result['plan']);
    }
}
