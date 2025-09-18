<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\BuildJob;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use DateTimeImmutable;
use PDO;

class PdoBuildQueueRepository implements BuildQueueRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return BuildJob[] */
    public function getActiveQueue(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM build_queue WHERE planet_id = :planet AND ends_at > NOW() ORDER BY ends_at ASC');
        $stmt->execute(['planet' => $planetId]);
        $jobs = [];

        while ($row = $stmt->fetch()) {
            $jobs[] = $this->hydrate($row);
        }

        return $jobs;
    }

    public function countActive(int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM build_queue WHERE planet_id = :planet AND ends_at > NOW()');
        $stmt->execute(['planet' => $planetId]);

        return (int) $stmt->fetchColumn();
    }

    public function enqueue(int $planetId, string $buildingKey, int $targetLevel, int $durationSeconds): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO build_queue (planet_id, bkey, target_level, ends_at)
            VALUES (:planet, :key, :level, DATE_ADD(NOW(), INTERVAL :duration SECOND))');
        $stmt->execute([
            'planet' => $planetId,
            'key' => $buildingKey,
            'level' => $targetLevel,
            'duration' => $durationSeconds,
        ]);
    }

    /** @return BuildJob[] */
    public function finalizeDueJobs(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM build_queue WHERE planet_id = :planet AND ends_at <= NOW() ORDER BY ends_at ASC');
        $stmt->execute(['planet' => $planetId]);
        $jobs = [];

        $ids = [];
        while ($row = $stmt->fetch()) {
            $jobs[] = $this->hydrate($row);
            $ids[] = (int) $row['id'];
        }

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $delete = $this->pdo->prepare('DELETE FROM build_queue WHERE id IN (' . $in . ')');
            $delete->execute($ids);
        }

        return $jobs;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BuildJob
    {
        return new BuildJob(
            (int) $row['id'],
            (int) $row['planet_id'],
            $row['bkey'],
            (int) $row['target_level'],
            new DateTimeImmutable($row['ends_at'])
        );
    }
}
