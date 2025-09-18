<?php

namespace App\Domain\Repository;

use App\Domain\Entity\ResearchJob;

interface ResearchQueueRepositoryInterface
{
    /** @return ResearchJob[] */
    public function getActiveQueue(int $planetId): array;

    public function countActive(int $planetId): int;

    public function enqueue(int $planetId, string $researchKey, int $targetLevel, int $durationSeconds): void;

    /** @return ResearchJob[] */
    public function finalizeDueJobs(int $planetId): array;
}
