<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\FleetRepositoryInterface;
use PDO;

class PdoFleetRepository implements FleetRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getFleet(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT skey, quantity FROM planet_fleet WHERE planet_id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $fleet = [];

        while ($row = $stmt->fetch()) {
            $fleet[$row['skey']] = (int) $row['quantity'];
        }

        return $fleet;
    }

    public function addShips(int $planetId, string $key, int $quantity): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO planet_fleet (planet_id, skey, quantity) VALUES (:planet, :key, :quantity)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)');
        $stmt->execute([
            'planet' => $planetId,
            'key' => $key,
            'quantity' => $quantity,
        ]);
    }
}
