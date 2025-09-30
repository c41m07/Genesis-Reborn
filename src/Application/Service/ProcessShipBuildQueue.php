<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;

class ProcessShipBuildQueue
{
    public function __construct(
        private readonly ShipBuildQueueRepositoryInterface $queue,
        private readonly FleetRepositoryInterface          $fleets
    ) {
    }

    public function process(int $planetId): void
    {
        $jobs = $this->queue->finalizeDueJobs($planetId);
        if ($jobs === []) {
            return;
        }

        foreach ($jobs as $job) {
            $this->fleets->addShips($planetId, $job->getShipKey(), $job->getQuantity());
        }
    }
}
