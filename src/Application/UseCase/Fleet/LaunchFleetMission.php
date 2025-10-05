<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Entity\Planet;
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
     * @param array<string, int> $resources
     * @param int|null $fleetId Identifiant de la flotte envoyée en mission
     *
     * @return array{success: bool, errors: list<string>, mission?: array<string, mixed>}
     */
    public function execute(
        int $userId,
        int $originPlanetId,
        array $composition,
        array $destination,
        float $speedFactor = 1.0,
        string $mission = 'transport',
        array $resources = [],
        ?int $fleetId = null
    ): array {
        $planResult = $this->planner->execute(
            $userId,
            $originPlanetId,
            $composition,
            $destination,
            $speedFactor,
            $mission,
            $resources,
            $fleetId
        );

        if ($planResult['success'] === false || $planResult['plan'] === null) {
            return [
                'success' => false,
                'errors' => $planResult['errors'],
            ];
        }

        if ($fleetId === null || $fleetId <= 0) {
            return [
                'success' => false,
                'errors' => ['Aucune flotte n’a été sélectionnée pour la mission.'],
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
        $cargoResources = $planResult['resources'] ?? [];
        $fuelRequired = (int)($plan['fuel'] ?? 0);
        $arrivalAt = $plan['arrival_time'];
        if (!$arrivalAt instanceof DateTimeImmutable) {
            $arrivalAt = new DateTimeImmutable($plan['arrival_time'] instanceof DateTimeImmutable ? $plan['arrival_time']->format(DATE_ATOM) : (string)$plan['arrival_time']);
        }

        $insufficient = [];
        $hydrogenCargo = (int)($cargoResources['hydrogen'] ?? 0);
        $totalHydrogenNeeded = $fuelRequired + $hydrogenCargo;
        if ($planet->getHydrogen() < $totalHydrogenNeeded) {
            $insufficient[] = 'Hydrogène insuffisant pour alimenter la flotte et charger la cargaison.';
        }

        $resourceCheckMap = [
            'metal' => static fn(Planet $planet): int => $planet->getMetal(),
            'crystal' => static fn(Planet $planet): int => $planet->getCrystal(),
        ];

        foreach ($resourceCheckMap as $resourceKey => $getter) {
            $required = (int)($cargoResources[$resourceKey] ?? 0);
            if ($required <= 0) {
                continue;
            }

            if ($getter($planet) < $required) {
                $insufficient[] = sprintf('Ressource %s insuffisante pour l’expédition.', $resourceKey);
            }
        }

        if ($insufficient !== []) {
            return [
                'success' => false,
                'errors' => $insufficient,
            ];
        }

        $planet->setHydrogen(max(0, $planet->getHydrogen() - $totalHydrogenNeeded));

        if (($cargoResources['metal'] ?? 0) > 0) {
            $planet->setMetal(max(0, $planet->getMetal() - (int)$cargoResources['metal']));
        }

        if (($cargoResources['crystal'] ?? 0) > 0) {
            $planet->setCrystal(max(0, $planet->getCrystal() - (int)$cargoResources['crystal']));
        }

        $this->planets->update($planet);

        $destinationPlanetId = $planResult['destination_planet_id'] ?? null;
        $missionPayload = [
            'cargo' => [
                'fuel' => $fuelRequired,
                'resources' => $cargoResources,
            ],
        ];

        if ($destinationPlanetId !== null) {
            $missionPayload['destination_planet_id'] = $destinationPlanetId;
        }
        $missionPayload['source_fleet_id'] = $fleetId;

        $movement = $this->movements->launchMission(
            $planet->getUserId(),
            $originPlanetId,
            $fleetId,
            $destinationPlanetId,
            $coordinates,
            $missionEnum,
            FleetStatus::Outbound,
            $planResult['composition'],
            (int)$plan['fuel'],
            new DateTimeImmutable(),
            $arrivalAt,
            (int)$plan['travel_time'],
            $missionPayload
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
            'resources' => $this->buildResourceSnapshot($planet),
        ];
    }

    /**
     * @return array<string, array{value: int, perHour: int, capacity: int}>
     */
    private function buildResourceSnapshot(Planet $planet): array
    {
        return [
            'metal' => [
                'value' => $planet->getMetal(),
                'perHour' => $planet->getMetalPerHour(),
                'capacity' => $planet->getMetalCapacity(),
            ],
            'crystal' => [
                'value' => $planet->getCrystal(),
                'perHour' => $planet->getCrystalPerHour(),
                'capacity' => $planet->getCrystalCapacity(),
            ],
            'hydrogen' => [
                'value' => $planet->getHydrogen(),
                'perHour' => $planet->getHydrogenPerHour(),
                'capacity' => $planet->getHydrogenCapacity(),
            ],
            'energy' => [
                'value' => $planet->getEnergy(),
                'perHour' => $planet->getEnergyPerHour(),
                'capacity' => $planet->getEnergyCapacity(),
            ],
        ];
    }
}
