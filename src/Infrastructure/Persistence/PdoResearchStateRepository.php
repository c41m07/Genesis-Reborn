<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\ResearchStateRepositoryInterface;
use PDO;
use RuntimeException;

class PdoResearchStateRepository implements ResearchStateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getLevels(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT t.`key` AS rkey, pt.level
            FROM player_technologies pt
            JOIN technologies t ON t.id = pt.technology_id
            WHERE pt.player_id = (SELECT player_id FROM planets WHERE id = :planet)');
        $stmt->execute(['planet' => $planetId]);
        $levels = [];

        while ($row = $stmt->fetch()) {
            $levels[$row['rkey']] = (int) $row['level'];
        }

        return $levels;
    }

    public function setLevel(int $planetId, string $key, int $level): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO player_technologies (player_id, technology_id, level, created_at, updated_at)
            SELECT p.player_id, t.id, :level, NOW(), NOW()
            FROM planets p
            JOIN technologies t ON t.`key` = :key
            WHERE p.id = :planet
            ON DUPLICATE KEY UPDATE level = VALUES(level), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'planet' => $planetId,
            'key' => $key,
            'level' => $level,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Impossible de mettre à jour le niveau de recherche demandé.');
        }
    }
}
