<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\PlayerStatsRepositoryInterface;
use PDO;

class PdoPlayerStatsRepository implements PlayerStatsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function addBuildingSpending(int $playerId, int $amount): void
    {
        $this->incrementColumn('building_spent', $playerId, $amount);
    }

    public function addScienceSpending(int $playerId, int $amount): void
    {
        $this->incrementColumn('science_spent', $playerId, $amount);
    }

    public function addFleetSpending(int $playerId, int $amount): void
    {
        $this->incrementColumn('fleet_spent', $playerId, $amount);
    }

    public function getBuildingSpending(int $playerId): int
    {
        return $this->getColumnValue('building_spent', $playerId);
    }

    public function getScienceSpending(int $playerId): int
    {
        return $this->getColumnValue('science_spent', $playerId);
    }

    public function getFleetSpending(int $playerId): int
    {
        return $this->getColumnValue('fleet_spent', $playerId);
    }

    private function incrementColumn(string $column, int $playerId, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare(sprintf('UPDATE players SET %1$s = %1$s + :amount WHERE id = :id', $column));
        $stmt->execute([
            'amount' => $amount,
            'id' => $playerId,
        ]);
    }

    private function getColumnValue(string $column, int $playerId): int
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT %s FROM players WHERE id = :id', $column));
        $stmt->execute(['id' => $playerId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (int)$value : 0;
    }
}
