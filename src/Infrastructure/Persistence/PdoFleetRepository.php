<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\FleetRepositoryInterface;
use PDO;
use RuntimeException;
use Throwable;

class PdoFleetRepository implements FleetRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getFleet(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT s.`key` AS skey, SUM(fs.quantity) AS quantity
            FROM fleets f
            JOIN fleet_ships fs ON fs.fleet_id = f.id
            JOIN ships s ON s.id = fs.ship_id
            WHERE f.origin_planet_id = :planet
              AND f.mission_type = :mission
              AND f.destination_planet_id IS NULL
              AND f.status IN (\'idle\', \'holding\')
            GROUP BY s.`key`');
        $stmt->execute([
            'planet' => $planetId,
            'mission' => 'idle',
        ]);
        $fleet = [];

        while ($row = $stmt->fetch()) {
            $fleet[$row['skey']] = (int) $row['quantity'];
        }

        return $fleet;
    }

    public function addShips(int $planetId, string $key, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $playerId = $this->getPlayerIdForPlanet($planetId);
            $fleetId = $this->ensureGarrisonFleet($playerId, $planetId);
            $shipId = $this->getShipIdByKey($key);

            if ($shipId === null) {
                throw new RuntimeException(sprintf('Type de vaisseau "%s" inconnu.', $key));
            }

            $stmt = $this->pdo->prepare('INSERT INTO fleet_ships (player_id, fleet_id, ship_id, quantity, created_at, updated_at)
                VALUES (:player, :fleet, :ship, :quantity, NOW(), NOW())
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = VALUES(updated_at)');
            $stmt->execute([
                'player' => $playerId,
                'fleet' => $fleetId,
                'ship' => $shipId,
                'quantity' => $quantity,
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function getPlayerIdForPlanet(int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT player_id FROM planets WHERE id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $playerId = $stmt->fetchColumn();

        if ($playerId === false) {
            throw new RuntimeException('Planète introuvable pour la flotte.');
        }

        return (int) $playerId;
    }

    private function ensureGarrisonFleet(int $playerId, int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM fleets
            WHERE player_id = :player
              AND origin_planet_id = :planet
              AND mission_type = :mission
              AND status = :status
              AND destination_planet_id IS NULL
            LIMIT 1 FOR UPDATE');
        $stmt->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'mission' => 'idle',
            'status' => 'idle',
        ]);
        $fleetId = $stmt->fetchColumn();

        if ($fleetId !== false) {
            return (int) $fleetId;
        }

        $create = $this->pdo->prepare('INSERT INTO fleets (player_id, origin_planet_id, destination_planet_id, mission_type, status, mission_payload, departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at)
            VALUES (:player, :planet, NULL, :mission, :status, NULL, NULL, NULL, NULL, 0, 0, NOW(), NOW())');
        $create->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'mission' => 'idle',
            'status' => 'idle',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function getShipIdByKey(string $key): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM ships WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $shipId = $stmt->fetchColumn();

        if ($shipId === false) {
            return null;
        }

        return (int) $shipId;
    }
}
