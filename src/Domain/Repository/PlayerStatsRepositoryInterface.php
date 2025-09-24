<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface PlayerStatsRepositoryInterface
{
    public function addScienceSpending(int $playerId, int $amount): void;

    public function getScienceSpending(int $playerId): int;
}
