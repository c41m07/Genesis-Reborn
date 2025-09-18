<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\ShipBuildJob;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class PdoShipBuildQueueRepository implements ShipBuildQueueRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return ShipBuildJob[] */
    public function getActiveQueue(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ship_build_queue WHERE planet_id = :planet AND ends_at > NOW() ORDER BY ends_at ASC');
        $stmt->execute(['planet' => $planetId]);

        $jobs = [];
        while ($row = $stmt->fetch()) {
            $jobs[] = $this->hydrate($row);
        }

        return $jobs;
    }

    public function countActive(int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM ship_build_queue WHERE planet_id = :planet AND ends_at > NOW()');
        $stmt->execute(['planet' => $planetId]);

        return (int) $stmt->fetchColumn();
    }

    public function enqueue(int $planetId, string $shipKey, int $quantity, int $durationSeconds): void
    {
        $playerId = $this->getPlayerIdForPlanet($planetId);

        $stmt = $this->pdo->prepare('INSERT INTO ship_build_queue (player_id, planet_id, skey, quantity, ends_at)
            VALUES (:player, :planet, :key, :quantity, DATE_ADD(NOW(), INTERVAL :duration SECOND))');
        $stmt->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'key' => $shipKey,
            'quantity' => $quantity,
            'duration' => $durationSeconds,
        ]);
    }

    /** @return ShipBuildJob[] */
    public function finalizeDueJobs(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ship_build_queue WHERE planet_id = :planet AND ends_at <= NOW() ORDER BY ends_at ASC');
        $stmt->execute(['planet' => $planetId]);

        $jobs = [];
        $ids = [];

        while ($row = $stmt->fetch()) {
            $jobs[] = $this->hydrate($row);
            $ids[] = (int) $row['id'];
        }

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $delete = $this->pdo->prepare('DELETE FROM ship_build_queue WHERE id IN (' . $in . ')');
            $delete->execute($ids);
        }

        return $jobs;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ShipBuildJob
    {
        return new ShipBuildJob(
            (int) $row['id'],
            (int) $row['planet_id'],
            $row['skey'],
            (int) $row['quantity'],
            new DateTimeImmutable($row['ends_at'])
        );
    }

    private function getPlayerIdForPlanet(int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT player_id FROM planets WHERE id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $playerId = $stmt->fetchColumn();

        if ($playerId === false) {
            throw new RuntimeException('Planète inconnue pour la file de construction spatiale.');
        }

        return (int) $playerId;
    }
}
