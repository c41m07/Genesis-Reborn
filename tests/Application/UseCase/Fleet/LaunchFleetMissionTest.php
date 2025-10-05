<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Fleet;

use App\Application\UseCase\Fleet\LaunchFleetMission;
use App\Application\UseCase\Fleet\PlanFleetMission;
use App\Domain\Entity\FleetMovement;
use App\Domain\Entity\Planet;
use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\ValueObject\Coordinates;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class LaunchFleetMissionTest extends TestCase
{
    public function testLaunchUsesPlanAndReturnsMissionMetadata(): void
    {
        $planUseCase = $this->createMock(PlanFleetMission::class);
        $arrival = new DateTimeImmutable('+1 hour');
        $cargoResources = ['metal' => 100, 'hydrogen' => 25];
        $planUseCase->expects(self::once())
            ->method('execute')
            ->with(
                42,
                7,
                ['fighter' => 5],
                ['galaxy' => 2, 'system' => 4, 'position' => 7],
                1.0,
                'transport',
                $cargoResources,
                15
            )
            ->willReturn([
                'success' => true,
                'errors' => [],
                'mission' => 'transport',
                'composition' => ['fighter' => 5],
                'destination' => ['galaxy' => 2, 'system' => 4, 'position' => 7],
                'resources' => $cargoResources,
                'destination_planet_id' => 12,
                'plan' => [
                    'distance' => 1200,
                    'speed' => 12,
                    'travel_time' => 3600,
                    'arrival_time' => $arrival,
                    'fuel' => 75,
                    'cargo_capacity' => 900,
                    'cargo_used' => 200,
                    'remaining_cargo' => 700,
                ],
            ]);

        $planet = new Planet(7, 42, 1, 2, 3, 'Gaia', 12000, -20, 30, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000);
        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->method('find')->with(7)->willReturn($planet);
        $planetRepository->expects(self::once())->method('update')->with(self::identicalTo($planet));

        $movement = new FleetMovement(
            15,
            42,
            7,
            null,
            Coordinates::fromInts(1, 2, 3),
            Coordinates::fromInts(2, 4, 7),
            FleetMission::Transport,
            FleetStatus::Outbound,
            ['fighter' => 5],
            new DateTimeImmutable(),
            $arrival,
            null,
            3600,
            75,
            []
        );

        $movementRepository = $this->createMock(FleetMovementRepositoryInterface::class);
        $movementRepository->expects(self::once())
            ->method('launchMission')
            ->with(
                42,
                7,
                15,
                12,
                self::callback(static function ($coordinates) {
                    return $coordinates instanceof Coordinates
                        && $coordinates->toArray() === ['galaxy' => 2, 'system' => 4, 'position' => 7];
                }),
                FleetMission::Transport,
                FleetStatus::Outbound,
                ['fighter' => 5],
                75,
                self::isInstanceOf(DateTimeImmutable::class),
                $arrival,
                3600,
                self::callback(static function (array $payload) use ($cargoResources): bool {
                    self::assertSame(75, $payload['cargo']['fuel']);
                    self::assertSame($cargoResources, $payload['cargo']['resources']);
                    self::assertSame(15, $payload['source_fleet_id']);

                    return true;
                })
            )
            ->willReturn($movement);

        $useCase = new LaunchFleetMission($planUseCase, $planetRepository, $movementRepository);
        $result = $useCase->execute(42, 7, ['fighter' => 5], ['galaxy' => 2, 'system' => 4, 'position' => 7], 1.0, 'transport', $cargoResources, 15);

        self::assertTrue($result['success']);
        self::assertSame([], $result['errors']);
        self::assertSame(
            [
                'id' => 15,
                'status' => 'outbound',
                'mission' => 'transport',
                'destination' => ['galaxy' => 2, 'system' => 4, 'position' => 7],
                'arrivalAt' => $arrival->format(DATE_ATOM),
            ],
            $result['mission']
        );
        self::assertIsArray($result['resources']);
        self::assertSame(
            [
                'value' => $planet->getMetal(),
                'perHour' => $planet->getMetalPerHour(),
                'capacity' => $planet->getMetalCapacity(),
            ],
            $result['resources']['metal']
        );
        self::assertSame(
            [
                'value' => $planet->getHydrogen(),
                'perHour' => $planet->getHydrogenPerHour(),
                'capacity' => $planet->getHydrogenCapacity(),
            ],
            $result['resources']['hydrogen']
        );
        self::assertSame(5000 - (75 + 25), $planet->getHydrogen());
        self::assertSame(4900, $planet->getMetal());
    }

    public function testLaunchReturnsErrorsWhenPlanFails(): void
    {
        $planUseCase = $this->createMock(PlanFleetMission::class);
        $planUseCase->method('execute')->willReturn([
            'success' => false,
            'errors' => ['invalid'],
            'mission' => 'transport',
            'composition' => [],
            'destination' => ['galaxy' => 1, 'system' => 1, 'position' => 1],
            'resources' => [],
            'destination_planet_id' => null,
            'plan' => null,
        ]);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $movementRepository = $this->createMock(FleetMovementRepositoryInterface::class);
        $movementRepository->expects(self::never())->method('launchMission');

        $useCase = new LaunchFleetMission($planUseCase, $planetRepository, $movementRepository);
        $result = $useCase->execute(42, 7, ['fighter' => 1], ['galaxy' => 1, 'system' => 1, 'position' => 1], 1.0, 'transport', [], null);

        self::assertFalse($result['success']);
        self::assertSame(['invalid'], $result['errors']);
        self::assertArrayNotHasKey('mission', $result);
    }

    public function testLaunchFailsWhenResourcesInsufficient(): void
    {
        $planUseCase = $this->createMock(PlanFleetMission::class);
        $planUseCase->method('execute')->willReturn([
            'success' => true,
            'errors' => [],
            'mission' => 'transport',
            'composition' => ['fighter' => 3],
            'destination' => ['galaxy' => 2, 'system' => 4, 'position' => 7],
            'resources' => ['metal' => 200, 'hydrogen' => 500],
            'destination_planet_id' => null,
            'plan' => [
                'distance' => 1200,
                'speed' => 12,
                'travel_time' => 3600,
                'arrival_time' => new DateTimeImmutable('+1 hour'),
                'fuel' => 200,
                'cargo_capacity' => 500,
                'cargo_used' => 400,
                'remaining_cargo' => 100,
            ],
        ]);

        $planet = new Planet(7, 42, 1, 2, 3, 'Gaia', 12000, -20, 30, 100, 5000, 300, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->method('find')->with(7)->willReturn($planet);
        $planetRepository->expects(self::never())->method('update');

        $movementRepository = $this->createMock(FleetMovementRepositoryInterface::class);
        $movementRepository->expects(self::never())->method('launchMission');

        $useCase = new LaunchFleetMission($planUseCase, $planetRepository, $movementRepository);
        $result = $useCase->execute(42, 7, ['fighter' => 3], ['galaxy' => 2, 'system' => 4, 'position' => 7], 1.0, 'transport', ['metal' => 200, 'hydrogen' => 500], 11);

        self::assertFalse($result['success']);
        self::assertNotEmpty($result['errors']);
    }
}
