<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface FleetRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function getFleet(int $planetId): array;

    public function addShips(int $planetId, string $key, int $quantity): void;

    /**
     * @return array<int, array{id: int, name: string|null, total: int, ships: array<string, int>, is_garrison: bool}>
     */
    public function listIdleFleets(int $planetId): array;

    public function createFleet(int $playerId, int $planetId, string $name): int;

    public function addShipsToFleet(int $fleetId, string $key, int $quantity): void;

    /**
     * @return array{id: int, player_id: int, origin_planet_id: int, name: string|null}|null
     */
    public function findIdleFleet(int $fleetId): ?array;

    public function renameFleet(int $fleetId, string $name): void;

    /**
     * @param array<string, int> $shipQuantities
     */
    public function transferShipsBetweenFleets(int $sourceFleetId, int $targetFleetId, array $shipQuantities, bool $deleteSourceIfEmpty): void;
}
