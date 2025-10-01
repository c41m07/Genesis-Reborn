<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Enum\FleetMission;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\FleetNavigationService;
use App\Domain\Service\ShipCatalog;
use App\Domain\ValueObject\Coordinates;
use DateTimeImmutable;

class PlanFleetMission
{
    public function __construct(
        private readonly PlanetRepositoryInterface        $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly FleetRepositoryInterface         $fleets,
        private readonly ShipCatalog                      $shipCatalog,
        private readonly FleetNavigationService           $navigation
    ) {
    }

    /**
     * @param array<string, int> $composition
     * @param array{galaxy?: int, system?: int, position?: int} $destination
     *
     * @return array{success: bool, errors: list<string>, mission: string, composition: array<string, int>, destination: array{galaxy: int, system: int, position: int}, plan: ?array{distance: int, speed: int, travel_time: int, arrival_time: DateTimeImmutable, fuel: int}}
     */
    public function execute(
        int $userId,
        int $originPlanetId,
        array $composition,
        array $destination,
        float $speedFactor = 1.0,
        string $mission = 'transport'
    ): array {
        $errors = [];
        $planet = $this->planets->find($originPlanetId);

        if ($planet === null || $planet->getUserId() !== $userId) {
            return [
                'success' => false,
                'errors' => ['Planète introuvable ou non autorisée.'],
                'mission' => $mission,
                'composition' => [],
                'destination' => $this->normalizeDestination($destination, [1, 1, 1]),
                'plan' => null,
            ];
        }

        $missionEnum = FleetMission::fromString($mission);

        $buildingLevels = $this->buildingStates->getLevels($originPlanetId);
        $shipyardLevel = (int)($buildingLevels['shipyard'] ?? 0);
        if ($shipyardLevel <= 0) {
            $errors[] = 'Le chantier spatial n’est pas disponible sur cette planète.';
        }

        $available = $this->fleets->getFleet($originPlanetId);
        $sanitizedComposition = $this->sanitizeComposition($composition, $available);
        if ($sanitizedComposition === []) {
            $errors[] = 'Sélectionnez au moins un vaisseau pour planifier une mission.';
        }

        $originCoordinates = Coordinates::fromArray($planet->getCoordinates());
        $targetCoordinates = $this->normalizeDestination($destination, $originCoordinates->toArray());
        $speedFactor = max(0.1, min(1.0, $speedFactor));

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'mission' => $missionEnum->value,
                'composition' => $sanitizedComposition,
                'destination' => $targetCoordinates,
                'plan' => null,
            ];
        }

        $shipStats = $this->buildShipStats($sanitizedComposition);

        try {
            $plan = $this->navigation->plan(
                $originCoordinates,
                Coordinates::fromArray($targetCoordinates),
                $sanitizedComposition,
                $shipStats,
                new DateTimeImmutable(),
                [],
                $speedFactor
            );
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();

            return [
                'success' => false,
                'errors' => $errors,
                'mission' => $missionEnum->value,
                'composition' => $sanitizedComposition,
                'destination' => $targetCoordinates,
                'plan' => null,
            ];
        }

        return [
            'success' => true,
            'errors' => [],
            'mission' => $missionEnum->value,
            'composition' => $sanitizedComposition,
            'destination' => $targetCoordinates,
            'plan' => $plan,
        ];
    }

    /**
     * @param array<string, int> $input
     * @param array<string, int> $available
     *
     * @return array<string, int>
     */
    private function sanitizeComposition(array $input, array $available): array
    {
        $composition = [];
        foreach ($input as $key => $value) {
            $quantity = max(0, (int)$value);
            if ($quantity === 0) {
                continue;
            }

            $availableQuantity = (int)($available[$key] ?? 0);
            if ($availableQuantity <= 0) {
                continue;
            }

            $composition[$key] = min($quantity, $availableQuantity);
        }

        return $composition;
    }

    /**
     * @param array{galaxy?: int, system?: int, position?: int} $destination
     * @param array{galaxy: int, system: int, position: int}|array{0: int, 1: int, 2: int} $fallback
     *
     * @return array{galaxy: int, system: int, position: int}
     */
    private function normalizeDestination(array $destination, array $fallback): array
    {
        if (isset($destination['galaxy'], $destination['system'], $destination['position'])) {
            return [
                'galaxy' => max(1, (int)$destination['galaxy']),
                'system' => max(1, (int)$destination['system']),
                'position' => max(1, (int)$destination['position']),
            ];
        }

        return [
            'galaxy' => max(1, (int)($fallback['galaxy'] ?? $fallback[0] ?? 1)),
            'system' => max(1, (int)($fallback['system'] ?? $fallback[1] ?? 1)),
            'position' => max(1, (int)($fallback['position'] ?? $fallback[2] ?? 1)),
        ];
    }

    /**
     * @param array<string, int> $composition
     *
     * @return array<string, array{speed: int, fuel_per_distance?: float}>
     */
    private function buildShipStats(array $composition): array
    {
        $stats = [];
        foreach ($composition as $shipKey => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $definition = $this->shipCatalog->get($shipKey);
            $shipStats = $definition->getStats();
            $speed = (int)($shipStats['vitesse'] ?? 0);
            $baseCost = $definition->getBaseCost();
            $fuelRate = (int)max(1, ceil(($baseCost['hydrogen'] ?? 0) / 25));

            $stats[$shipKey] = [
                'speed' => $speed > 0 ? $speed : max(1, $fuelRate * 2),
                'fuel_per_distance' => max(0.1, $fuelRate ?: 1),
            ];
        }

        return $stats;
    }
}
