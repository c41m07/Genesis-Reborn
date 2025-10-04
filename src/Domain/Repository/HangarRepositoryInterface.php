<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface HangarRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function getStock(int $planetId): array;

    public function getQuantity(int $planetId, string $shipKey): int;

    public function addShips(int $planetId, string $shipKey, int $quantity): void;

    public function removeShips(int $planetId, string $shipKey, int $quantity): void;
}
