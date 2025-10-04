<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\HangarRepositoryInterface;
use PDO;
use RuntimeException;
use Throwable;

final class PdoHangarRepository implements HangarRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getStock(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT s.`key` AS skey, phs.quantity
            FROM planet_hangar_ships phs
            JOIN ships s ON s.id = phs.ship_id
            WHERE phs.planet_id = :planet
            ORDER BY s.`key`');
        $stmt->execute(['planet' => $planetId]);

        $stock = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stock[$row['skey']] = (int)$row['quantity'];
        }

        return $stock;
    }

    public function getQuantity(int $planetId, string $shipKey): int
    {
        $shipId = $this->getShipIdByKey($shipKey);
        if ($shipId === null) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT quantity FROM planet_hangar_ships
            WHERE planet_id = :planet AND ship_id = :ship LIMIT 1');
        $stmt->execute([
            'planet' => $planetId,
            'ship' => $shipId,
        ]);

        $quantity = $stmt->fetchColumn();

        return $quantity === false ? 0 : (int)$quantity;
    }

    public function addShips(int $planetId, string $shipKey, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $playerId = $this->getPlayerIdForPlanet($planetId);
            $shipId = $this->requireShipIdByKey($shipKey);

            $current = $this->lockRow($playerId, $planetId, $shipId);

            if ($current === null) {
                $stmt = $this->pdo->prepare('INSERT INTO planet_hangar_ships (player_id, planet_id, ship_id, quantity)
                    VALUES (:player, :planet, :ship, :quantity)');
                $stmt->execute([
                    'player' => $playerId,
                    'planet' => $planetId,
                    'ship' => $shipId,
                    'quantity' => $quantity,
                ]);
            } else {
                $stmt = $this->pdo->prepare('UPDATE planet_hangar_ships
                    SET quantity = quantity + :quantity, updated_at = CURRENT_TIMESTAMP
                    WHERE player_id = :player AND planet_id = :planet AND ship_id = :ship');
                $stmt->execute([
                    'player' => $playerId,
                    'planet' => $planetId,
                    'ship' => $shipId,
                    'quantity' => $quantity,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function removeShips(int $planetId, string $shipKey, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $playerId = $this->getPlayerIdForPlanet($planetId);
            $shipId = $this->requireShipIdByKey($shipKey);

            $current = $this->lockRow($playerId, $planetId, $shipId);

            if ($current === null || $current < $quantity) {
                throw new RuntimeException('Quantité insuffisante dans le hangar.');
            }

            $stmt = $this->pdo->prepare('UPDATE planet_hangar_ships
                SET quantity = quantity - :quantity, updated_at = CURRENT_TIMESTAMP
                WHERE player_id = :player AND planet_id = :planet AND ship_id = :ship');
            $stmt->execute([
                'player' => $playerId,
                'planet' => $planetId,
                'ship' => $shipId,
                'quantity' => $quantity,
            ]);

            $stmt = $this->pdo->prepare('DELETE FROM planet_hangar_ships
                WHERE player_id = :player AND planet_id = :planet AND ship_id = :ship AND quantity <= 0');
            $stmt->execute([
                'player' => $playerId,
                'planet' => $planetId,
                'ship' => $shipId,
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
            throw new RuntimeException('Planète introuvable pour le hangar.');
        }

        return (int)$playerId;
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

    private function requireShipIdByKey(string $key): int
    {
        $shipId = $this->getShipIdByKey($key);
        if ($shipId === null) {
            throw new RuntimeException(sprintf('Type de vaisseau "%s" inconnu.', $key));
        }

        return $shipId;
    }

    private function lockRow(int $playerId, int $planetId, int $shipId): ?int
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = 'SELECT quantity FROM planet_hangar_ships WHERE player_id = :player AND planet_id = :planet AND ship_id = :ship';
        if ($driver === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'ship' => $shipId,
        ]);

        $quantity = $stmt->fetchColumn();

        return $quantity === false ? null : (int)$quantity;
    }
}
