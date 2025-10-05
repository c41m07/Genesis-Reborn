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
    private const UNITS_PER_ASTRONOMICAL_UNIT = 16.0;

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
     * @param array<string, int> $resources
     * @param int|null $fleetId Identifiant de la flotte sélectionnée (ou null pour la garnison)
     *
     * @return array{
     *     success: bool,
     *     errors: list<string>,
     *     mission: string,
     *     composition: array<string, int>,
     *     destination: array{galaxy: int, system: int, position: int},
     *     resources: array<string, int>,
     *     destination_planet_id: ?int,
     *     plan: ?array{
     *         distance: float,
     *         speed: float,
     *         travel_time: int,
     *         arrival_time: DateTimeImmutable,
     *         fuel: int,
     *         cargo_capacity: int,
     *         cargo_used: int,
     *         remaining_cargo: int,
     *     }
     * }
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
        $errors = [];
        $planet = $this->planets->find($originPlanetId);

        if ($planet === null || $planet->getUserId() !== $userId) {
            return [
                'success' => false,
                'errors' => ['Planète introuvable ou non autorisée.'],
                'mission' => $mission,
                'composition' => [],
                'destination' => $this->normalizeDestination($destination, [1, 1, 1]),
                'resources' => [],
                'destination_planet_id' => null,
                'plan' => null,
            ];
        }

        $missionEnum = FleetMission::fromString($mission);

        $buildingLevels = $this->buildingStates->getLevels($originPlanetId);
        $shipyardLevel = (int)($buildingLevels['shipyard'] ?? 0);
        if ($shipyardLevel <= 0) {
            $errors[] = 'Le chantier spatial n’est pas disponible sur cette planète.';
        }

        $available = $this->resolveAvailableShips($userId, $originPlanetId, $fleetId);
        $missionComposition = $this->buildFullComposition($available);
        if ($fleetId !== null && $available === []) {
            $errors[] = 'Flotte sélectionnée introuvable ou indisponible.';
        } elseif ($missionComposition === []) {
            $errors[] = 'Aucun vaisseau disponible sur cette planète.';
        }

        $originCoordinates = Coordinates::fromArray($planet->getCoordinates());
        $targetCoordinates = $this->normalizeDestination($destination, $originCoordinates->toArray());
        $sanitizedResources = $this->sanitizeResources($resources);
        $speedFactor = max(0.1, min(1.0, $speedFactor));
        $destinationPlanetId = $this->resolveDestinationPlanetId($targetCoordinates);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'mission' => $missionEnum->value,
                'composition' => $missionComposition,
                'destination' => $targetCoordinates,
                'resources' => $sanitizedResources,
                'destination_planet_id' => $destinationPlanetId,
                'plan' => null,
            ];
        }

        $shipStats = $this->buildShipStats($missionComposition);

        try {
            $plan = $this->navigation->plan(
                $originCoordinates,
                Coordinates::fromArray($targetCoordinates),
                $missionComposition,
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
                'composition' => $missionComposition,
                'destination' => $targetCoordinates,
                'resources' => $sanitizedResources,
                'destination_planet_id' => $destinationPlanetId,
                'plan' => null,
            ];
        }

        $totalCargoCapacity = $this->calculateFleetCargoCapacity($missionComposition, $shipStats);
        $fuelLoad = (int)($plan['fuel'] ?? 0);
        $cargoResources = array_sum($sanitizedResources);
        $cargoUsed = $fuelLoad + $cargoResources;
        $remainingCargo = $totalCargoCapacity - $cargoUsed;

        if ($remainingCargo < 0) {
            $errors[] = 'Capacité de soute insuffisante pour embarquer carburant et ressources.';

            return [
                'success' => false,
                'errors' => $errors,
                'mission' => $missionEnum->value,
                'composition' => $missionComposition,
                'destination' => $targetCoordinates,
                'resources' => $sanitizedResources,
                'destination_planet_id' => $destinationPlanetId,
                'plan' => null,
            ];
        }

        $plan['cargo_capacity'] = $totalCargoCapacity;
        $plan['cargo_used'] = $cargoUsed;
        $plan['remaining_cargo'] = max(0, $remainingCargo);

        return [
            'success' => true,
            'errors' => [],
            'mission' => $missionEnum->value,
            'composition' => $missionComposition,
            'destination' => $targetCoordinates,
            'resources' => $sanitizedResources,
            'destination_planet_id' => $destinationPlanetId,
            'plan' => $plan,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function resolveAvailableShips(int $userId, int $planetId, ?int $fleetId): array
    {
        if ($fleetId === null) {
            return $this->fleets->getFleet($planetId);
        }

        $fleet = $this->fleets->findIdleFleet($fleetId);
        if ($fleet === null) {
            return [];
        }

        if ((int)($fleet['player_id'] ?? 0) !== $userId || (int)($fleet['origin_planet_id'] ?? 0) !== $planetId) {
            return [];
        }

        $ships = is_array($fleet['ships'] ?? null) ? $fleet['ships'] : [];

        return array_filter(
            array_map(static fn ($value): int => (int)$value, $ships),
            static fn (int $quantity): bool => $quantity > 0
        );
    }

    /**
     * @param array<string, int> $input
     * @param array<string, int> $available
     *
     * @return array<string, int>
     */
    private function buildFullComposition(array $available): array
    {
        $composition = [];
        foreach ($available as $key => $value) {
            $quantity = (int)$value;
            if ($quantity <= 0) {
                continue;
            }

            $composition[(string)$key] = $quantity;
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
     * @return array<string, array{speed: float, fuel_per_hour?: float, cargo_capacity: int}>
     */
    private function buildShipStats(array $composition): array
    {
        $stats = [];
        foreach ($composition as $shipKey => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $definition = $this->shipCatalog->get($shipKey);
            $logistics = $definition->getLogistics();
            $speedUnitsPerHour = (float)($logistics['speed'] ?? ($definition->getStats()['vitesse'] ?? 0));
            if ($speedUnitsPerHour <= 0) {
                $speedUnitsPerHour = 1.0;
            }

            $uaSpeed = $speedUnitsPerHour / self::UNITS_PER_ASTRONOMICAL_UNIT;
            $consumption = max(0.0, (float)($logistics['consumption'] ?? 0.0));
            $cargoCapacity = max(0, $definition->getCargoCapacity());

            $stats[$shipKey] = [
                'speed' => max(0.01, $uaSpeed),
                'fuel_per_hour' => $consumption,
                'cargo_capacity' => $cargoCapacity,
            ];
        }

        return $stats;
    }

    /**
     * @param array<string, int> $resources
     *
     * @return array<string, int>
     */
    private function sanitizeResources(array $resources): array
    {
        $sanitized = [];
        $allowed = ['metal', 'crystal', 'hydrogen'];
        foreach ($resources as $key => $amount) {
            $resourceKey = (string)$key;
            if (!in_array($resourceKey, $allowed, true)) {
                continue;
            }

            $sanitized[$resourceKey] = max(0, (int)$amount);
        }

        return $sanitized;
    }

    /**
     * @param array<string, int> $composition
     * @param array<string, array{speed: float, fuel_per_hour?: float, cargo_capacity: int}> $shipStats
     */
    private function calculateFleetCargoCapacity(array $composition, array $shipStats): int
    {
        $capacity = 0;
        foreach ($composition as $shipKey => $quantity) {
            $shipCargo = (int)($shipStats[$shipKey]['cargo_capacity'] ?? 0);
            if ($shipCargo <= 0 || $quantity <= 0) {
                continue;
            }

            $capacity += $shipCargo * $quantity;
        }

        return $capacity;
    }

    /**
     * @param array{galaxy: int, system: int, position: int} $coordinates
     */
    private function resolveDestinationPlanetId(array $coordinates): ?int
    {
        $planets = $this->planets->findByCoordinates($coordinates['galaxy'], $coordinates['system']);

        foreach ($planets as $planet) {
            if ($planet->getPosition() === $coordinates['position']) {
                return $planet->getId();
            }
        }

        return null;
    }
}
