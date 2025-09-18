<?php

namespace App\Domain\Repository;

interface BuildingStateRepositoryInterface
{
    /** @return array<string, int> */
    public function getLevels(int $planetId): array;

    public function setLevel(int $planetId, string $buildingKey, int $level): void;
}
