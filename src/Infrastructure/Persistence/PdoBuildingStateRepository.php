<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\BuildingStateRepositoryInterface;
use PDO;

class PdoBuildingStateRepository implements BuildingStateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getLevels(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT bkey, level FROM planet_buildings WHERE planet_id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $levels = [];

        while ($row = $stmt->fetch()) {
            $levels[$row['bkey']] = (int) $row['level'];
        }

        return $levels;
    }

    public function setLevel(int $planetId, string $buildingKey, int $level): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO planet_buildings (planet_id, bkey, level) VALUES (:planet, :key, :level)
            ON DUPLICATE KEY UPDATE level = VALUES(level)');
        $stmt->execute([
            'planet' => $planetId,
            'key' => $buildingKey,
            'level' => $level,
        ]);
    }
}
