<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Service\Queue\QueueFinalizer;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;

class ProcessResearchQueue
{
    public function __construct(
        private readonly ResearchQueueRepositoryInterface $queue,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly QueueFinalizer $finalizer
    ) {
    }

    public function process(int $planetId): void
    {
        $this->finalizer->finalize(
            $planetId,
            fn (int $id): array => $this->queue->finalizeDueJobs($id),
            function (array $jobs) use ($planetId): void {
                /** @var array<int, \App\Domain\Entity\ResearchJob> $jobs */
                foreach ($jobs as $job) {
                    $this->researchStates->setLevel($planetId, $job->getResearchKey(), $job->getTargetLevel());
                }
            }
        );
    }
}
