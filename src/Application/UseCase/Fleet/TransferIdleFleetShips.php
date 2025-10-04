<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use RuntimeException;

final class TransferIdleFleetShips
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly FleetRepositoryInterface $fleets
    ) {
    }

    /**
     * @param array<string, int> $shipQuantities
     *
     * @return array{success: bool, message: string}
     */
    public function execute(
        int $playerId,
        int $planetId,
        int $sourceFleetId,
        ?int $targetFleetId,
        array $shipQuantities,
        bool $sendToHangar
    ): array {
        $planet = $this->planets->find($planetId);
        if ($planet === null || $planet->getUserId() !== $playerId) {
            return [
                'success' => false,
                'message' => 'Planète inaccessible.',
            ];
        }

        $sourceMeta = $this->fleets->findIdleFleet($sourceFleetId);
        if ($sourceMeta === null || $sourceMeta['player_id'] !== $playerId || $sourceMeta['origin_planet_id'] !== $planetId) {
            return [
                'success' => false,
                'message' => 'Flotte introuvable ou inaccessible.',
            ];
        }

        $fleetsOnPlanet = $this->fleets->listIdleFleets($planetId);
        $sourceSummary = null;
        $targetSummary = null;
        $garrisonSummary = null;

        foreach ($fleetsOnPlanet as $summary) {
            if ($summary['id'] === $sourceFleetId) {
                $sourceSummary = $summary;
            }
            if ($summary['is_garrison']) {
                $garrisonSummary = $summary;
            }
            if ($targetFleetId !== null && $summary['id'] === $targetFleetId) {
                $targetSummary = $summary;
            }
        }

        if ($sourceSummary === null) {
            return [
                'success' => false,
                'message' => 'Flotte introuvable ou inaccessible.',
            ];
        }

        $targetIsHangar = $targetSummary !== null && ($targetSummary['is_garrison'] ?? false) === true;

        if ($sendToHangar || $targetIsHangar) {
            if ($sourceSummary['is_garrison']) {
                return [
                    'success' => false,
                    'message' => 'La garnison ne peut pas être transférée vers le hangar.',
                ];
            }

            if ($garrisonSummary === null) {
                return [
                    'success' => false,
                    'message' => 'Aucune garnison disponible pour recevoir les vaisseaux.',
                ];
            }

            $targetFleetId = $garrisonSummary['id'];
            $targetSummary = $garrisonSummary;
            $sendToHangar = true;
        } else {
            if ($targetFleetId === null || $targetFleetId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Veuillez sélectionner une destination valide.',
                ];
            }

            if ($targetFleetId === $sourceFleetId) {
                return [
                    'success' => false,
                    'message' => 'Veuillez sélectionner une flotte différente pour le transfert.',
                ];
            }

            if ($targetSummary === null) {
                return [
                    'success' => false,
                    'message' => 'Flotte de destination introuvable ou inaccessible.',
                ];
            }

            $targetMeta = $this->fleets->findIdleFleet($targetFleetId);
            if ($targetMeta === null || $targetMeta['player_id'] !== $playerId || $targetMeta['origin_planet_id'] !== $planetId) {
                return [
                    'success' => false,
                    'message' => 'Flotte de destination introuvable ou inaccessible.',
                ];
            }
        }

        $quantities = [];
        foreach ($shipQuantities as $shipKey => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity <= 0) {
                continue;
            }

            if (!isset($sourceSummary['ships'][$shipKey])) {
                return [
                    'success' => false,
                    'message' => 'Sélection de vaisseau invalide pour cette flotte.',
                ];
            }

            if ($quantity > (int)$sourceSummary['ships'][$shipKey]) {
                return [
                    'success' => false,
                    'message' => 'Quantité demandée supérieure au stock disponible.',
                ];
            }

            $quantities[$shipKey] = $quantity;
        }

        if ($quantities === []) {
            return [
                'success' => false,
                'message' => 'Veuillez sélectionner au moins un vaisseau à transférer.',
            ];
        }

        $deleteSourceIfEmpty = $sendToHangar && !$sourceSummary['is_garrison'];

        try {
            $this->fleets->transferShipsBetweenFleets($sourceFleetId, $targetSummary['id'], $quantities, $deleteSourceIfEmpty);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $destinationLabel = $targetSummary['is_garrison'] ? 'le hangar planétaire' : sprintf('la flotte "%s"', $targetSummary['label'] ?? '');
        $movedShips = array_sum($quantities);

        return [
            'success' => true,
            'message' => sprintf('%d vaisseau(x) ont été transférés vers %s.', $movedShips, $destinationLabel),
        ];
    }
}
