<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use RuntimeException;

final class CreateIdleFleet
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly FleetRepositoryInterface $fleets
    ) {
    }

    /**
     * @return array{success: bool, message: string, fleetId?: int}
     */
    public function execute(int $playerId, int $planetId, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [
                'success' => false,
                'message' => 'Le nom de la flotte ne peut pas être vide.',
            ];
        }

        if (mb_strlen($name) < 3) {
            return [
                'success' => false,
                'message' => 'Le nom de la flotte doit contenir au moins 3 caractères.',
            ];
        }

        if (mb_strlen($name) > 100) {
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

        try {
            $fleetId = $this->fleets->createFleet($playerId, $planetId, $name);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('La flotte "%s" a été créée avec succès.', $name),
            'fleetId' => $fleetId,
        ];
    }
}
