<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface PlayerStatsRepositoryInterface
{
    public function addBuildingSpending(int $playerId, int $amount): void;

    public function addScienceSpending(int $playerId, int $amount): void;

    public function addFleetSpending(int $playerId, int $amount): void;

    public function getBuildingSpending(int $playerId): int;

    public function getScienceSpending(int $playerId): int;

    public function getFleetSpending(int $playerId): int;
}
