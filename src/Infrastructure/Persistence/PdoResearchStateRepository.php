<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\ResearchStateRepositoryInterface;
use PDO;

class PdoResearchStateRepository implements ResearchStateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getLevels(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT rkey, level FROM planet_research WHERE planet_id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $levels = [];

        while ($row = $stmt->fetch()) {
            $levels[$row['rkey']] = (int) $row['level'];
        }

        return $levels;
    }

    public function setLevel(int $planetId, string $key, int $level): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO planet_research (planet_id, rkey, level) VALUES (:planet, :key, :level)
            ON DUPLICATE KEY UPDATE level = :level');
        $stmt->execute([
            'planet' => $planetId,
            'key' => $key,
            'level' => $level,
        ]);
    }
}
