<?php

declare(strict_types=1);

namespace App\Application\UseCase\Fleet;

use App\Domain\Repository\FleetMovementRepositoryInterface;
use DateTimeImmutable;

final class ProcessFleetReturns
{
    public function __construct(private readonly FleetMovementRepositoryInterface $movements)
    {
    }

    public function execute(int $playerId, DateTimeImmutable $now): int
    {
        $returns = $this->movements->findReturningMissions($now, $playerId);
        $count = 0;

        foreach ($returns as $movement) {
            $this->movements->completeReturn($movement, $now);
            $count++;
        }

        return $count;
    }
}
