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
        $planetRepository->expects(self::once())
            ->method('findByCoordinates')
            ->with(2, 5)
            ->willReturn([]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->expects(self::once())
            ->method('getLevels')
            ->with(7)
            ->willReturn(['shipyard' => 2]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->expects(self::once())
            ->method('findIdleFleet')
            ->with(15)
            ->willReturn([
                'id' => 15,
                'player_id' => 42,
                'origin_planet_id' => 7,
                'name' => 'Flotte Alpha',
                'ships' => ['fighter' => 10],
            ]);
        $fleetRepository->expects(self::never())->method('getFleet');

        $definition = new ShipDefinition(
            'fighter',
            'Chasseur',
            'light',
            'Interception',
            'Unité polyvalente',
            ['metal' => 100, 'hydrogen' => 50],
            60,
            ['vitesse' => 360],
            [],
            'fighter.png',
            ['speed' => 360, 'consumption' => 9, 'cargo' => 60]
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
            'transport',
            ['metal' => 120, 'hydrogen' => 40, 'invalid' => 999],
            15
        );

        self::assertTrue($result['success']);
        self::assertSame(['fighter' => 10], $result['composition']);
        self::assertSame(['galaxy' => 2, 'system' => 5, 'position' => 9], $result['destination']);
        self::assertSame('transport', $result['mission']);
        self::assertSame(['metal' => 120, 'hydrogen' => 40], $result['resources']);
        self::assertNotNull($result['plan']);
        self::assertInstanceOf(DateTimeImmutable::class, $result['plan']['arrival_time']);
        self::assertArrayHasKey('remaining_cargo', $result['plan']);
        self::assertSame(
            $result['plan']['cargo_capacity'],
            $result['plan']['cargo_used'] + $result['plan']['remaining_cargo']
        );
        self::assertGreaterThanOrEqual(0, $result['plan']['remaining_cargo']);
    }

    public function testPlanIgnoresRequestedSubsetAndUsesAllAvailableShips(): void
    {
        $planet = new Planet(5, 7, 1, 5, 9, 'Ares', 8000, -10, 25, 4000, 4000, 4000, 0, 0, 0, 0, 0, 60000, 60000, 60000, 800);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->expects(self::once())
            ->method('find')
            ->with(5)
            ->willReturn($planet);
        $planetRepository->expects(self::once())
            ->method('findByCoordinates')
            ->with(5, 9)
            ->willReturn([]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->expects(self::once())
            ->method('getLevels')
            ->with(5)
            ->willReturn(['shipyard' => 4]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->expects(self::once())
            ->method('findIdleFleet')
            ->with(99)
            ->willReturn([
                'id' => 99,
                'player_id' => 7,
                'origin_planet_id' => 5,
                'name' => 'Flotte',
                'ships' => ['fighter' => 8],
            ]);

        $definition = new ShipDefinition(
            'fighter',
            'Chasseur',
            'light',
            'Interception',
            'Unité polyvalente',
            ['metal' => 100, 'hydrogen' => 50],
            60,
            ['vitesse' => 360],
            [],
            'fighter.png',
            ['speed' => 360, 'consumption' => 9, 'cargo' => 80]
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
            7,
            5,
            ['fighter' => 2],
            ['galaxy' => 5, 'system' => 9, 'position' => 3],
            1.0,
            'transport',
            [],
            99
        );

        self::assertTrue($result['success']);
        self::assertSame(['fighter' => 8], $result['composition']);
    }

    public function testPlanFailsWhenCargoInsufficient(): void
    {
        $planet = new Planet(7, 42, 1, 2, 3, 'Gaia', 12000, -20, 30, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->method('find')->willReturn($planet);
        $planetRepository->expects(self::once())
            ->method('findByCoordinates')
            ->with(2, 5)
            ->willReturn([]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->method('getLevels')->willReturn(['shipyard' => 2]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->method('findIdleFleet')->willReturn([
            'id' => 12,
            'player_id' => 42,
            'origin_planet_id' => 7,
            'name' => 'Flotte',
            'ships' => ['fighter' => 5],
        ]);

        $definition = new ShipDefinition(
            'fighter',
            'Chasseur',
            'light',
            'Interception',
            'Unité polyvalente',
            ['metal' => 100, 'hydrogen' => 50],
            60,
            ['vitesse' => 360],
            [],
            'fighter.png',
            ['speed' => 360, 'consumption' => 9, 'cargo' => 30]
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
            ['fighter' => 5],
            ['galaxy' => 2, 'system' => 5, 'position' => 9],
            1.0,
            'transport',
            ['metal' => 5000, 'crystal' => 2000],
            12
        );

        self::assertFalse($result['success']);
        self::assertNotEmpty($result['errors']);
        self::assertSame('transport', $result['mission']);
        self::assertNull($result['plan']);
    }

    public function testPlanFailsWhenShipyardMissing(): void
    {
        $planet = new Planet(3, 99, 1, 1, 1, 'Outpost', 9000, -10, 20, 2000, 2000, 2000, 0, 0, 0, 0, 0, 50000, 50000, 50000, 500);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->method('find')->willReturn($planet);
        $planetRepository->expects(self::never())->method('findByCoordinates');

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

    public function testColonizationRequiresColonyShip(): void
    {
        $planet = new Planet(9, 51, 2, 4, 6, 'Origine', 9000, -10, 30, 3000, 3000, 3000, 0, 0, 0, 0, 0, 80000, 80000, 80000, 600);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->expects(self::once())->method('find')->with(9)->willReturn($planet);
        $planetRepository->expects(self::once())->method('findByCoordinates')->with(4, 6)->willReturn([]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->expects(self::once())->method('getLevels')->with(9)->willReturn(['shipyard' => 3]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->expects(self::once())
            ->method('findIdleFleet')
            ->with(21)
            ->willReturn([
                'id' => 21,
                'player_id' => 51,
                'origin_planet_id' => 9,
                'ships' => ['fighter' => 5],
            ]);

        $shipCatalog = $this->createMock(ShipCatalog::class);

        $useCase = new PlanFleetMission(
            $planetRepository,
            $buildingStates,
            $fleetRepository,
            $shipCatalog,
            new FleetNavigationService()
        );

        $result = $useCase->execute(
            51,
            9,
            [],
            ['galaxy' => 2, 'system' => 4, 'position' => 6],
            1.0,
            'colonize',
            [],
            21
        );

        self::assertFalse($result['success']);
        self::assertContains(
            'Un vaisseau de colonisation est requis pour établir une nouvelle colonie.',
            $result['errors']
        );
        self::assertSame('colonize', $result['mission']);
    }

    public function testColonizationFailsWhenTargetOccupied(): void
    {
        $planet = new Planet(5, 7, 1, 2, 3, 'Origine', 9000, -20, 20, 2000, 2000, 2000, 0, 0, 0, 0, 0, 60000, 60000, 60000, 500);
        $occupied = new Planet(18, 999, 2, 5, 8, 'Gaïa Prime', 11000, -15, 25, 1000, 1000, 1000, 0, 0, 0, 0, 0, 40000, 40000, 40000, 300);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->expects(self::once())->method('find')->with(5)->willReturn($planet);
        $planetRepository->expects(self::once())->method('findByCoordinates')->with(5, 8)->willReturn([$occupied]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->expects(self::once())->method('getLevels')->with(5)->willReturn(['shipyard' => 4]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->expects(self::once())
            ->method('findIdleFleet')
            ->with(14)
            ->willReturn([
                'id' => 14,
                'player_id' => 7,
                'origin_planet_id' => 5,
                'ships' => ['colony_ship' => 1],
            ]);

        $colonyDefinition = new ShipDefinition(
            'colony_ship',
            'Arche',
            'utility',
            'Colonisation',
            'Déploie des modules de colonie.',
            ['metal' => 1000],
            3600,
            ['vitesse' => 160],
            [],
            'colony.svg',
            ['speed' => 160, 'consumption' => 420, 'cargo' => 2500]
        );

        $shipCatalog = $this->createMock(ShipCatalog::class);
        $shipCatalog->expects(self::once())->method('get')->with('colony_ship')->willReturn($colonyDefinition);

        $useCase = new PlanFleetMission(
            $planetRepository,
            $buildingStates,
            $fleetRepository,
            $shipCatalog,
            new FleetNavigationService()
        );

        $result = $useCase->execute(
            7,
            5,
            ['colony_ship' => 1],
            ['galaxy' => 2, 'system' => 5, 'position' => 8],
            1.0,
            'colonize',
            [],
            14
        );

        self::assertFalse($result['success']);
        self::assertContains(
            'La position ciblée est déjà occupée : impossible de coloniser.',
            $result['errors']
        );
    }

    public function testColonizationPlanSucceedsWithAvailableColonyShip(): void
    {
        $planet = new Planet(6, 11, 1, 3, 7, 'Origine', 9500, -12, 28, 4000, 4000, 4000, 0, 0, 0, 0, 0, 75000, 75000, 75000, 650);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->expects(self::once())->method('find')->with(6)->willReturn($planet);
        $planetRepository->expects(self::once())->method('findByCoordinates')->with(3, 7)->willReturn([]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->expects(self::once())->method('getLevels')->with(6)->willReturn(['shipyard' => 5]);

        $fleetRepository = $this->createMock(FleetRepositoryInterface::class);
        $fleetRepository->expects(self::once())
            ->method('findIdleFleet')
            ->with(33)
            ->willReturn([
                'id' => 33,
                'player_id' => 11,
                'origin_planet_id' => 6,
                'ships' => ['colony_ship' => 1],
            ]);

        $colonyDefinition = new ShipDefinition(
            'colony_ship',
            'Arche',
            'utility',
            'Colonisation',
            'Déploie des modules de colonie.',
            ['metal' => 1000],
            3600,
            ['vitesse' => 160],
            [],
            'colony.svg',
            ['speed' => 160, 'consumption' => 420, 'cargo' => 2500]
        );

        $shipCatalog = $this->createMock(ShipCatalog::class);
        $shipCatalog->expects(self::once())->method('get')->with('colony_ship')->willReturn($colonyDefinition);

        $useCase = new PlanFleetMission(
            $planetRepository,
            $buildingStates,
            $fleetRepository,
            $shipCatalog,
            new FleetNavigationService()
        );

        $result = $useCase->execute(
            11,
            6,
            ['colony_ship' => 1],
            ['galaxy' => 2, 'system' => 3, 'position' => 9],
            1.0,
            'colonize',
            [],
            33
        );

        self::assertTrue($result['success']);
        self::assertSame('colonize', $result['mission']);
        self::assertSame(['colony_ship' => 1], $result['composition']);
        self::assertNull($result['destination_planet_id']);
        self::assertNotNull($result['plan']);
        self::assertArrayHasKey('remaining_cargo', $result['plan']);
    }
}
