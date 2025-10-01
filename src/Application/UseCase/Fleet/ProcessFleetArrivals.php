<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetMovementRepositoryInterface;
use DateTimeImmutable;

class ProcessFleetArrivals
{
    public function __construct(private readonly FleetMovementRepositoryInterface $movements)
    {
    }

    public function execute(int $playerId, DateTimeImmutable $now): int
    {
        $arrivals = $this->movements->findArrivedMissions($now, $playerId);
        $count = 0;

        foreach ($arrivals as $movement) {
            $this->movements->completeArrival($movement, $now);
            $count++;
        }

        return $count;
    }
}
