<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\BuildingStateRepositoryInterface;
use PDO;
use RuntimeException;

class PdoBuildingStateRepository implements BuildingStateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getLevels(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT b.`key` AS bkey, pb.level FROM planet_buildings pb
            JOIN buildings b ON b.id = pb.building_id
            WHERE pb.planet_id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $levels = [];

        while ($row = $stmt->fetch()) {
            $levels[$row['bkey']] = (int)$row['level'];
        }

        return $levels;
    }

    public function setLevel(int $planetId, string $buildingKey, int $level): void
    {
        $playerId = $this->getPlayerIdForPlanet($planetId);
        $buildingId = $this->getBuildingIdByKey($buildingKey);

        $stmt = $this->pdo->prepare('INSERT INTO planet_buildings (player_id, planet_id, building_id, level, created_at, updated_at)
            VALUES (:player, :planet, :building, :level, NOW(), NOW())
            ON DUPLICATE KEY UPDATE level = VALUES(level), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'building' => $buildingId,
            'level' => $level,
        ]);
    }

    private function getPlayerIdForPlanet(int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT player_id FROM planets WHERE id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $playerId = $stmt->fetchColumn();

        if ($playerId === false) {
            throw new RuntimeException('Planète introuvable pour la mise à jour des bâtiments.');
        }

        return (int)$playerId;
    }

    private function getBuildingIdByKey(string $buildingKey): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM buildings WHERE `key` = :key');
        $stmt->execute(['key' => $buildingKey]);
        $buildingId = $stmt->fetchColumn();

        if ($buildingId === false) {
            throw new RuntimeException(sprintf('Bâtiment "%s" introuvable.', $buildingKey));
        }

        return (int)$buildingId;
    }
}
