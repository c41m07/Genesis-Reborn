<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Service\Queue\QueueFinalizer;
use App\Domain\Repository\HangarRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;

class ProcessShipBuildQueue
{
    public function __construct(
        private readonly ShipBuildQueueRepositoryInterface $queue,
        private readonly HangarRepositoryInterface         $hangars,
        private readonly QueueFinalizer                    $finalizer
    ) {
    }

    public function process(int $planetId): void
    {
        $this->finalizer->finalize(
            $planetId,
            fn (int $id): array => $this->queue->finalizeDueJobs($id),
            function (array $jobs) use ($planetId): void {
                /** @var array<int, \App\Domain\Entity\ShipBuildJob> $jobs */
                foreach ($jobs as $job) {
                    $this->hangars->addShips($planetId, $job->getShipKey(), $job->getQuantity());
                }
            }
        );
    }
}
