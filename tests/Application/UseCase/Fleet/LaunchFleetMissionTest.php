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
        $planUseCase->expects(self::once())
            ->method('execute')
            ->willReturn([
                'success' => true,
                'errors' => [],
                'mission' => 'transport',
                'composition' => ['fighter' => 5],
                'destination' => ['galaxy' => 2, 'system' => 4, 'position' => 7],
                'plan' => [
                    'distance' => 1200,
                    'speed' => 12,
                    'travel_time' => 3600,
                    'arrival_time' => $arrival,
                    'fuel' => 75,
                ],
            ]);

        $planet = new Planet(7, 42, 1, 2, 3, 'Gaia', 12000, -20, 30, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000);
        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $planetRepository->method('find')->with(7)->willReturn($planet);

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
            ->willReturn($movement);

        $useCase = new LaunchFleetMission($planUseCase, $planetRepository, $movementRepository);
        $result = $useCase->execute(42, 7, ['fighter' => 5], ['galaxy' => 2, 'system' => 4, 'position' => 7], 1.0, 'transport');

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
            'plan' => null,
        ]);

        $planetRepository = $this->createMock(PlanetRepositoryInterface::class);
        $movementRepository = $this->createMock(FleetMovementRepositoryInterface::class);
        $movementRepository->expects(self::never())->method('launchMission');

        $useCase = new LaunchFleetMission($planUseCase, $planetRepository, $movementRepository);
        $result = $useCase->execute(42, 7, ['fighter' => 1], ['galaxy' => 1, 'system' => 1, 'position' => 1], 1.0, 'transport');

        self::assertFalse($result['success']);
        self::assertSame(['invalid'], $result['errors']);
        self::assertArrayNotHasKey('mission', $result);
    }
}
