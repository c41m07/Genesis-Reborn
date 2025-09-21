<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\PlayerStatsRepositoryInterface;
use PDO;

class PdoPlayerStatsRepository implements PlayerStatsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function addScienceSpending(int $playerId, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE players SET science_spent = science_spent + :amount WHERE id = :id');
        $stmt->execute([
            'amount' => $amount,
            'id' => $playerId,
        ]);
    }

    public function getScienceSpending(int $playerId): int
    {
        $stmt = $this->pdo->prepare('SELECT science_spent FROM players WHERE id = :id');
        $stmt->execute(['id' => $playerId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (int) $value : 0;
    }
}
