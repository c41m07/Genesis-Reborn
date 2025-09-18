<?php

namespace App\Application\Service;

use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;

class ProcessResearchQueue
{
    public function __construct(
        private readonly ResearchQueueRepositoryInterface $queue,
        private readonly ResearchStateRepositoryInterface $researchStates
    ) {
    }

    public function process(int $planetId): void
    {
        $jobs = $this->queue->finalizeDueJobs($planetId);
        if ($jobs === []) {
            return;
        }

        foreach ($jobs as $job) {
            $this->researchStates->setLevel($planetId, $job->getResearchKey(), $job->getTargetLevel());
        }
    }
}
