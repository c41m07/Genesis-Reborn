<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Planet;

interface PlanetRepositoryInterface
{
    /** @return Planet[] */
    public function findByUser(int $userId): array;

    public function find(int $id): ?Planet;

    /** @return Planet[] */
    public function findByCoordinates(int $galaxy, int $system): array;

    public function createHomeworld(int $userId): Planet;

    public function update(Planet $planet): void;

    public function rename(int $planetId, string $name): void;
}
