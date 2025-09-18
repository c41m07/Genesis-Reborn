<?php

namespace App\Domain\Repository;

use App\Domain\Entity\ShipBuildJob;

interface ShipBuildQueueRepositoryInterface
{
    /** @return ShipBuildJob[] */
    public function getActiveQueue(int $planetId): array;

    public function countActive(int $planetId): int;

    public function enqueue(int $planetId, string $shipKey, int $quantity, int $durationSeconds): void;

    /** @return ShipBuildJob[] */
    public function finalizeDueJobs(int $planetId): array;
}
