<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\HangarRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use RuntimeException;

final class AssembleFleetFromHangar
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly HangarRepositoryInterface $hangars,
        private readonly FleetRepositoryInterface $fleets
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(
        int $playerId,
        int $planetId,
        string $shipKey,
        int $quantity,
        ?int $targetFleetId = null,
        ?string $newFleetName = null
    ): array
    {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'Veuillez sélectionner une quantité positive.',
            ];
        }

        if ($newFleetName !== null) {
            $newFleetName = trim($newFleetName);
            if ($newFleetName === '') {
                return [
                    'success' => false,
                    'message' => 'Le nom de la flotte ne peut pas être vide.',
                ];
            }

            if (mb_strlen($newFleetName) < 3) {
                return [
                    'success' => false,
                    'message' => 'Le nom de la flotte doit contenir au moins 3 caractères.',
                ];
            }

            if (mb_strlen($newFleetName) > 100) {
                return [
                    'success' => false,
                    'message' => 'Le nom de la flotte ne peut pas dépasser 100 caractères.',
                ];
            }
        }

        if ($newFleetName !== null && $targetFleetId !== null) {
            return [
                'success' => false,
                'message' => 'Veuillez choisir soit une flotte existante, soit en créer une nouvelle.',
            ];
        }

        $planet = $this->planets->find($planetId);
        if ($planet === null || $planet->getUserId() !== $playerId) {
            return [
                'success' => false,
                'message' => 'Planète inaccessible.',
            ];
        }

        $available = $this->hangars->getQuantity($planetId, $shipKey);
        if ($available < $quantity) {
            return [
                'success' => false,
                'message' => 'Stock insuffisant dans le hangar.',
            ];
        }

        $fleetLabel = 'garnison orbitale';
        $targetFleetId = $targetFleetId !== null && $targetFleetId > 0 ? $targetFleetId : null;

        if ($newFleetName !== null) {
            $targetFleetId = $this->fleets->createFleet($playerId, $planetId, $newFleetName);
            $fleetLabel = $newFleetName;
        } elseif ($targetFleetId !== null) {
            $fleet = $this->fleets->findIdleFleet($targetFleetId);
            if ($fleet === null || $fleet['player_id'] !== $playerId || $fleet['origin_planet_id'] !== $planetId) {
                return [
                    'success' => false,
                    'message' => 'Flotte introuvable ou inaccessible.',
                ];
            }

            $fleetLabel = $fleet['name'] !== null ? $fleet['name'] : $fleetLabel;
        }

        try {
            $this->hangars->removeShips($planetId, $shipKey, $quantity);
            if ($targetFleetId !== null) {
                $this->fleets->addShipsToFleet($targetFleetId, $shipKey, $quantity);
            } else {
                $this->fleets->addShips($planetId, $shipKey, $quantity);
            }
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        if ($fleetLabel === 'garnison orbitale') {
            $message = 'Garnison renforcée avec succès.';
        } else {
            $message = sprintf('Flotte "%s" renforcée avec succès.', $fleetLabel);
        }

        return [
            'success' => true,
            'message' => $message,
        ];
    }
}
