<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use RuntimeException;

final class RenameIdleFleet
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly FleetRepositoryInterface $fleets
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(int $playerId, int $planetId, int $fleetId, string $newName): array
    {
        $newName = trim($newName);
        if ($newName === '') {
            return [
                'success' => false,
                'message' => 'Le nom de la flotte ne peut pas être vide.',
            ];
        }

        if (mb_strlen($newName) < 3) {
            return [
                'success' => false,
                'message' => 'Le nom de la flotte doit contenir au moins 3 caractères.',
            ];
        }

        if (mb_strlen($newName) > 100) {
            return [
                'success' => false,
                'message' => 'Le nom de la flotte ne peut pas dépasser 100 caractères.',
            ];
        }

        $planet = $this->planets->find($planetId);
        if ($planet === null || $planet->getUserId() !== $playerId) {
            return [
                'success' => false,
                'message' => 'Planète inaccessible.',
            ];
        }

        $fleet = $this->fleets->findIdleFleet($fleetId);
        if ($fleet === null || $fleet['player_id'] !== $playerId || $fleet['origin_planet_id'] !== $planetId) {
            return [
                'success' => false,
                'message' => 'Flotte introuvable ou inaccessible.',
            ];
        }

        if ($fleet['name'] === null) {
            return [
                'success' => false,
                'message' => 'La garnison ne peut pas être renommée.',
            ];
        }

        try {
            $this->fleets->renameFleet($fleetId, $newName);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('La flotte "%s" a été renommée avec succès.', $newName),
        ];
    }
}
