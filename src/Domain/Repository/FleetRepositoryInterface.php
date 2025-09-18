<?php

namespace App\Domain\Repository;

interface FleetRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function getFleet(int $planetId): array;

    public function addShips(int $planetId, string $key, int $quantity): void;
}
