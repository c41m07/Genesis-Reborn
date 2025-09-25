<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\BuildJob;

interface BuildQueueRepositoryInterface
{
    /** @return BuildJob[] */
    public function getActiveQueue(int $planetId): array;

    public function countActive(int $planetId): int;

    public function enqueue(int $planetId, string $buildingKey, int $targetLevel, int $durationSeconds): void;

    /** @return BuildJob[] */
    public function finalizeDueJobs(int $planetId): array;
}
