<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use RuntimeException;

final class DeleteIdleFleet
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly FleetRepositoryInterface $fleets
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(int $playerId, int $planetId, int $fleetId): array
    {
        $planet = $this->planets->find($planetId);
        if ($planet === null || $planet->getUserId() !== $playerId) {
            return [
                'success' => false,
                'message' => 'Planète inaccessible.',
            ];
        }

        $fleetMeta = $this->fleets->findIdleFleet($fleetId);
        if ($fleetMeta === null || $fleetMeta['player_id'] !== $playerId || $fleetMeta['origin_planet_id'] !== $planetId) {
            return [
                'success' => false,
                'message' => 'Flotte introuvable ou inaccessible.',
            ];
        }

        if ($fleetMeta['name'] === null) {
            return [
                'success' => false,
                'message' => 'La garnison ne peut pas être supprimée.',
            ];
        }

        $fleetsOnPlanet = $this->fleets->listIdleFleets($planetId);
        $garrisonSummary = null;
        $fleetSummary = null;

        foreach ($fleetsOnPlanet as $summary) {
            if ($summary['is_garrison']) {
                $garrisonSummary = $summary;
            }
            if ($summary['id'] === $fleetId) {
                $fleetSummary = $summary;
            }
        }

        if ($garrisonSummary === null) {
            return [
                'success' => false,
                'message' => 'Aucune garnison disponible pour récupérer les vaisseaux.',
            ];
        }

        $ships = [];
        if ($fleetSummary !== null && !empty($fleetSummary['ships'])) {
            foreach ($fleetSummary['ships'] as $shipKey => $quantity) {
                $quantity = (int)$quantity;
                if ($quantity <= 0) {
                    continue;
                }

                $ships[$shipKey] = $quantity;
            }
        }

        try {
            $this->fleets->transferShipsBetweenFleets($fleetId, $garrisonSummary['id'], $ships, true);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $name = $fleetMeta['name'] ?? 'Flotte';

        return [
            'success' => true,
            'message' => sprintf('La flotte "%s" a été dissoute. Les vaisseaux ont été renvoyés au hangar.', $name),
        ];
    }
}
