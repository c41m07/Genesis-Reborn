<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\FleetRepositoryInterface;
use InvalidArgumentException;
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
            $fleet[$row['skey']] = (int)$row['quantity'];
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
            $this->insertFleetShip($fleetId, $playerId, $key, $quantity);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function listIdleFleets(int $planetId): array
    {
        $playerId = $this->getPlayerIdForPlanet($planetId);
        $garrisonId = $this->ensureGarrisonFleet($playerId, $planetId);

        $stmt = $this->pdo->prepare(<<<SQL
            SELECT f.id, f.name, f.created_at, s.`key` AS ship_key, fs.quantity
            FROM fleets f
            LEFT JOIN fleet_ships fs ON fs.fleet_id = f.id
            LEFT JOIN ships s ON s.id = fs.ship_id
            WHERE f.origin_planet_id = :planet
              AND f.mission_type = :mission
              AND f.destination_planet_id IS NULL
              AND f.status IN ('idle','holding')
            ORDER BY f.created_at , s.`key` 
        SQL);
        $stmt->execute([
            'planet' => $planetId,
            'mission' => 'idle',
        ]);

        $fleets = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['id'];
            if (!isset($fleets[$id])) {
                $fleets[$id] = [
                    'id' => $id,
                    'name' => $row['name'] !== null ? (string)$row['name'] : null,
                    'total' => 0,
                    'ships' => [],
                    'is_garrison' => $id === $garrisonId,
                ];
            }

            if ($row['ship_key'] !== null) {
                $fleets[$id]['ships'][$row['ship_key']] = (int)$row['quantity'];
                $fleets[$id]['total'] += (int)$row['quantity'];
            }
        }

        return array_values($fleets);
    }

    public function createFleet(int $playerId, int $planetId, string $name): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO fleets (player_id, origin_planet_id, destination_planet_id, name, mission_type, status, mission_payload, departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at)
            VALUES (:player, :planet, NULL, :name, :mission, :status, NULL, NULL, NULL, NULL, 0, 0, NOW(), NOW())');
        $stmt->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'name' => $name,
            'mission' => 'idle',
            'status' => 'idle',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function addShipsToFleet(int $fleetId, string $key, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $fleetInfo = $this->getFleetOwnerAndPlanet($fleetId);
            if ($fleetInfo === null) {
                throw new RuntimeException('Flotte introuvable.');
            }

            $this->insertFleetShip($fleetId, $fleetInfo['player_id'], $key, $quantity);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function findIdleFleet(int $fleetId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, player_id, origin_planet_id, name
            FROM fleets
            WHERE id = :id
              AND destination_planet_id IS NULL
              AND mission_type = :mission
              AND status IN (\'idle\', \'holding\')
            LIMIT 1');
        $stmt->execute([
            'id' => $fleetId,
            'mission' => 'idle',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'player_id' => (int)$row['player_id'],
            'origin_planet_id' => (int)$row['origin_planet_id'],
            'name' => $row['name'] !== null ? (string)$row['name'] : null,
        ];
    }

    public function renameFleet(int $fleetId, string $name): void
    {
        $stmt = $this->pdo->prepare('UPDATE fleets SET name = :name, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $fleetId,
            'name' => $name,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Flotte introuvable.');
        }
    }

    public function transferShipsBetweenFleets(int $sourceFleetId, int $targetFleetId, array $shipQuantities, bool $deleteSourceIfEmpty): void
    {
        if ($sourceFleetId === $targetFleetId) {
            throw new InvalidArgumentException('Les flottes source et destination doivent être distinctes.');
        }

        $this->pdo->beginTransaction();

        try {
            $sourceInfo = $this->getFleetOwnerAndPlanet($sourceFleetId);
            $targetInfo = $this->getFleetOwnerAndPlanet($targetFleetId);

            if ($sourceInfo === null || $targetInfo === null) {
                throw new RuntimeException('Flotte introuvable.');
            }

            foreach ($shipQuantities as $shipKey => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $shipId = $this->getShipIdByKey($shipKey);
                if ($shipId === null) {
                    throw new RuntimeException(sprintf('Type de vaisseau "%s" inconnu.', $shipKey));
                }

                $select = $this->pdo->prepare('SELECT quantity FROM fleet_ships WHERE fleet_id = :fleet AND ship_id = :ship FOR UPDATE');
                $select->execute([
                    'fleet' => $sourceFleetId,
                    'ship' => $shipId,
                ]);
                $current = $select->fetchColumn();

                if ($current === false) {
                    throw new RuntimeException('Quantité insuffisante pour le transfert.');
                }

                $currentQuantity = (int)$current;
                if ($currentQuantity < $quantity) {
                    throw new RuntimeException('Quantité insuffisante pour le transfert.');
                }

                $remaining = $currentQuantity - $quantity;
                if ($remaining > 0) {
                    $update = $this->pdo->prepare('UPDATE fleet_ships SET quantity = :quantity, updated_at = NOW() WHERE fleet_id = :fleet AND ship_id = :ship');
                    $update->execute([
                        'quantity' => $remaining,
                        'fleet' => $sourceFleetId,
                        'ship' => $shipId,
                    ]);
                } else {
                    $delete = $this->pdo->prepare('DELETE FROM fleet_ships WHERE fleet_id = :fleet AND ship_id = :ship');
                    $delete->execute([
                        'fleet' => $sourceFleetId,
                        'ship' => $shipId,
                    ]);
                }

                $this->insertFleetShip($targetFleetId, $targetInfo['player_id'], $shipKey, $quantity);
            }

            $touch = $this->pdo->prepare('UPDATE fleets SET updated_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $sourceFleetId]);
            $touch->execute(['id' => $targetFleetId]);

            if ($deleteSourceIfEmpty) {
                $count = $this->pdo->prepare('SELECT COUNT(*) FROM fleet_ships WHERE fleet_id = :fleet');
                $count->execute(['fleet' => $sourceFleetId]);
                $remainingShips = (int)$count->fetchColumn();

                if ($remainingShips === 0) {
                    $deleteFleet = $this->pdo->prepare('DELETE FROM fleets WHERE id = :fleet');
                    $deleteFleet->execute(['fleet' => $sourceFleetId]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function removeShipsFromFleet(int $fleetId, array $shipQuantities, bool $deleteFleetIfEmpty): void
    {
        $this->pdo->beginTransaction();

        try {
            $fleetInfo = $this->getFleetOwnerAndPlanet($fleetId);

            if ($fleetInfo === null) {
                throw new RuntimeException('Flotte introuvable.');
            }

            foreach ($shipQuantities as $shipKey => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $shipId = $this->getShipIdByKey($shipKey);
                if ($shipId === null) {
                    throw new RuntimeException(sprintf('Type de vaisseau "%s" inconnu.', $shipKey));
                }

                $select = $this->pdo->prepare('SELECT quantity FROM fleet_ships WHERE fleet_id = :fleet AND ship_id = :ship FOR UPDATE');
                $select->execute([
                    'fleet' => $fleetId,
                    'ship' => $shipId,
                ]);
                $current = $select->fetchColumn();

                if ($current === false) {
                    throw new RuntimeException('Quantité insuffisante pour le transfert.');
                }

                $currentQuantity = (int)$current;
                if ($currentQuantity < $quantity) {
                    throw new RuntimeException('Quantité insuffisante pour le transfert.');
                }

                $remaining = $currentQuantity - $quantity;
                if ($remaining > 0) {
                    $update = $this->pdo->prepare('UPDATE fleet_ships SET quantity = :quantity, updated_at = NOW() WHERE fleet_id = :fleet AND ship_id = :ship');
                    $update->execute([
                        'quantity' => $remaining,
                        'fleet' => $fleetId,
                        'ship' => $shipId,
                    ]);
                } else {
                    $delete = $this->pdo->prepare('DELETE FROM fleet_ships WHERE fleet_id = :fleet AND ship_id = :ship');
                    $delete->execute([
                        'fleet' => $fleetId,
                        'ship' => $shipId,
                    ]);
                }
            }

            $touch = $this->pdo->prepare('UPDATE fleets SET updated_at = NOW() WHERE id = :id');
            $touch->execute(['id' => $fleetId]);

            if ($deleteFleetIfEmpty) {
                $count = $this->pdo->prepare('SELECT COUNT(*) FROM fleet_ships WHERE fleet_id = :fleet');
                $count->execute(['fleet' => $fleetId]);
                $remainingShips = (int)$count->fetchColumn();

                if ($remainingShips === 0) {
                    $deleteFleet = $this->pdo->prepare('DELETE FROM fleets WHERE id = :fleet');
                    $deleteFleet->execute(['fleet' => $fleetId]);
                }
            }

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

        return (int)$playerId;
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
            return (int)$fleetId;
        }

        $create = $this->pdo->prepare('INSERT INTO fleets (player_id, origin_planet_id, destination_planet_id, name, mission_type, status, mission_payload, departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at)
            VALUES (:player, :planet, NULL, NULL, :mission, :status, NULL, NULL, NULL, NULL, 0, 0, NOW(), NOW())');
        $create->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'mission' => 'idle',
            'status' => 'idle',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @return array{player_id: int, planet_id: int}|null
     */
    private function getFleetOwnerAndPlanet(int $fleetId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT player_id, origin_planet_id FROM fleets WHERE id = :fleet LIMIT 1 FOR UPDATE');
        $stmt->execute(['fleet' => $fleetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'player_id' => (int)$row['player_id'],
            'planet_id' => (int)$row['origin_planet_id'],
        ];
    }

    private function insertFleetShip(int $fleetId, int $playerId, string $key, int $quantity): void
    {
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
    }

    private function getShipIdByKey(string $key): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM ships WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $shipId = $stmt->fetchColumn();

        if ($shipId === false) {
            return null;
        }

        return (int)$shipId;
    }
}
