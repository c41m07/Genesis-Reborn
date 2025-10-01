<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\ValueObject\Coordinates;
use DateTimeImmutable;

class LaunchFleetMission
{
    public function __construct(
        private readonly PlanFleetMission               $planner,
        private readonly PlanetRepositoryInterface      $planets,
        private readonly FleetMovementRepositoryInterface $movements,
    ) {
    }

    /**
     * @param array<string, int> $composition
     * @param array{galaxy?: int, system?: int, position?: int} $destination
     *
     * @return array{success: bool, errors: list<string>, mission?: array<string, mixed>}
     */
    public function execute(
        int $userId,
        int $originPlanetId,
        array $composition,
        array $destination,
        float $speedFactor = 1.0,
        string $mission = 'transport'
    ): array {
        $planResult = $this->planner->execute(
            $userId,
            $originPlanetId,
            $composition,
            $destination,
            $speedFactor,
            $mission
        );

        if ($planResult['success'] === false || $planResult['plan'] === null) {
            return [
                'success' => false,
                'errors' => $planResult['errors'],
            ];
        }

        $planet = $this->planets->find($originPlanetId);
        if ($planet === null) {
            return [
                'success' => false,
                'errors' => ['Planète introuvable après validation.'],
            ];
        }

        $missionEnum = FleetMission::fromString($planResult['mission']);
        $coordinates = Coordinates::fromArray($planResult['destination']);
        $plan = $planResult['plan'];
        $arrivalAt = $plan['arrival_time'];
        if (!$arrivalAt instanceof DateTimeImmutable) {
            $arrivalAt = new DateTimeImmutable($plan['arrival_time'] instanceof DateTimeImmutable ? $plan['arrival_time']->format(DATE_ATOM) : (string)$plan['arrival_time']);
        }

        $movement = $this->movements->launchMission(
            $planet->getUserId(),
            $originPlanetId,
            null,
            $coordinates,
            $missionEnum,
            FleetStatus::Outbound,
            $planResult['composition'],
            (int)$plan['fuel'],
            new DateTimeImmutable(),
            $arrivalAt,
            (int)$plan['travel_time']
        );

        return [
            'success' => true,
            'errors' => [],
            'mission' => [
                'id' => $movement->getId(),
                'status' => $movement->getStatus()->value,
                'mission' => $movement->getMission()->value,
                'destination' => $movement->getDestination()->toArray(),
                'arrivalAt' => $movement->getArrivalAt()?->format(DATE_ATOM),
            ],
        ];
    }
}
