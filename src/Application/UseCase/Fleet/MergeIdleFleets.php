<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use RuntimeException;

final class MergeIdleFleets
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly FleetRepositoryInterface $fleets
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(
        int $playerId,
        int $planetId,
        int $sourceFleetId,
        int $targetFleetId,
        string $mode,
        ?string $shipKey = null,
        ?int $quantity = null
    ): array {
        $mode = $mode === 'all' ? 'all' : 'partial';

        if ($sourceFleetId === $targetFleetId) {
            return [
                'success' => false,
                'message' => 'Veuillez sélectionner deux flottes distinctes.',
            ];
        }

        $planet = $this->planets->find($planetId);
        if ($planet === null || $planet->getUserId() !== $playerId) {
            return [
                'success' => false,
                'message' => 'Planète inaccessible.',
            ];
        }

        $source = $this->fleets->findIdleFleet($sourceFleetId);
        $target = $this->fleets->findIdleFleet($targetFleetId);

        if ($source === null || $target === null || $source['player_id'] !== $playerId || $target['player_id'] !== $playerId) {
            return [
                'success' => false,
                'message' => 'Flotte introuvable ou inaccessible.',
            ];
        }

        if ($source['origin_planet_id'] !== $planetId || $target['origin_planet_id'] !== $planetId) {
            return [
                'success' => false,
                'message' => 'Les flottes doivent être stationnées sur la même planète.',
            ];
        }

        $fleetsOnPlanet = $this->fleets->listIdleFleets($planetId);
        $sourceSummary = null;
        $targetSummary = null;

        foreach ($fleetsOnPlanet as $summary) {
            if ($summary['id'] === $sourceFleetId) {
                $sourceSummary = $summary;
            }
            if ($summary['id'] === $targetFleetId) {
                $targetSummary = $summary;
            }
        }

        if ($sourceSummary === null || $targetSummary === null) {
            return [
                'success' => false,
                'message' => 'Flotte introuvable ou inaccessible.',
            ];
        }

        if ($sourceSummary['total'] === 0) {
            return [
                'success' => false,
                'message' => 'La flotte source ne contient aucun vaisseau.',
            ];
        }

        $shipQuantities = [];
        $deleteSourceIfEmpty = false;

        if ($mode === 'all') {
            $shipQuantities = $sourceSummary['ships'];
            if (empty($shipQuantities)) {
                return [
                    'success' => false,
                    'message' => 'Aucun vaisseau à transférer.',
                ];
            }
            $deleteSourceIfEmpty = !$sourceSummary['is_garrison'];
        } else {
            $shipKey = $shipKey !== null ? trim($shipKey) : '';
            if ($shipKey === '' || !isset($sourceSummary['ships'][$shipKey])) {
                return [
                    'success' => false,
                    'message' => 'Sélection de vaisseau invalide pour cette flotte.',
                ];
            }

            if ($quantity === null || $quantity <= 0) {
                return [
                    'success' => false,
                    'message' => 'Veuillez indiquer une quantité positive à transférer.',
                ];
            }

            $available = (int)$sourceSummary['ships'][$shipKey];
            if ($quantity > $available) {
                return [
                    'success' => false,
                    'message' => 'Quantité demandée supérieure au stock disponible.',
                ];
            }

            $shipQuantities = [$shipKey => $quantity];
        }

        try {
            $this->fleets->transferShipsBetweenFleets($sourceFleetId, $targetFleetId, $shipQuantities, $deleteSourceIfEmpty);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $sourceLabel = $this->formatFleetLabel($sourceSummary['name'], $sourceSummary['is_garrison']);
        $targetLabel = $this->formatFleetLabel($targetSummary['name'], $targetSummary['is_garrison']);

        if ($mode === 'all') {
            return [
                'success' => true,
                'message' => sprintf('La flotte "%s" a été fusionnée avec "%s".', $sourceLabel, $targetLabel),
            ];
        }

        $shipKey = (string)array_key_first($shipQuantities);
        $quantity = (int)$shipQuantities[$shipKey];

        return [
            'success' => true,
            'message' => sprintf('%d unité(s) de %s transférées de "%s" vers "%s".', $quantity, $shipKey, $sourceLabel, $targetLabel),
        ];
    }

    private function formatFleetLabel(?string $name, bool $isGarrison): string
    {
        if ($isGarrison) {
            return 'Garnison orbitale';
        }

        return $name ?? 'Flotte sans nom';
    }
}
