<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\ResearchJob;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class PdoResearchQueueRepository implements ResearchQueueRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return ResearchJob[] */
    public function getActiveQueue(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM research_queue WHERE planet_id = :planet AND ends_at > NOW() ORDER BY ends_at ASC');
        $stmt->execute(['planet' => $planetId]);

        $jobs = [];
        while ($row = $stmt->fetch()) {
            $jobs[] = $this->hydrate($row);
        }

        return $jobs;
    }

    public function countActive(int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM research_queue WHERE planet_id = :planet AND ends_at > NOW()');
        $stmt->execute(['planet' => $planetId]);

        return (int) $stmt->fetchColumn();
    }

    public function enqueue(int $planetId, string $researchKey, int $targetLevel, int $durationSeconds): void
    {
        $playerId = $this->getPlayerIdForPlanet($planetId);

        $lastEndStatement = $this->pdo->prepare('SELECT MAX(ends_at) FROM research_queue WHERE planet_id = :planet');
        $lastEndStatement->execute(['planet' => $planetId]);
        $lastEndsAt = $lastEndStatement->fetchColumn();

        $startAt = new DateTimeImmutable();
        if ($lastEndsAt !== false && $lastEndsAt !== null) {
            $lastEndsAtTime = new DateTimeImmutable((string) $lastEndsAt);
            if ($lastEndsAtTime > $startAt) {
                $startAt = $lastEndsAtTime;
            }
        }

        $duration = max(0, $durationSeconds);
        $endsAt = $startAt->add(new DateInterval(sprintf('PT%dS', $duration)));

        $stmt = $this->pdo->prepare('INSERT INTO research_queue (player_id, planet_id, rkey, target_level, ends_at)
            VALUES (:player, :planet, :key, :level, :endsAt)');
        $stmt->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'key' => $researchKey,
            'level' => $targetLevel,
            'endsAt' => $endsAt->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return ResearchJob[] */
    public function finalizeDueJobs(int $planetId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM research_queue WHERE planet_id = :planet AND ends_at <= NOW() ORDER BY ends_at ASC');
        $stmt->execute(['planet' => $planetId]);

        $jobs = [];
        $ids = [];

        while ($row = $stmt->fetch()) {
            $jobs[] = $this->hydrate($row);
            $ids[] = (int) $row['id'];
        }

        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $delete = $this->pdo->prepare('DELETE FROM research_queue WHERE id IN (' . $in . ')');
            $delete->execute($ids);
        }

        return $jobs;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ResearchJob
    {
        return new ResearchJob(
            (int) $row['id'],
            (int) $row['planet_id'],
            $row['rkey'],
            (int) $row['target_level'],
            new DateTimeImmutable($row['ends_at'])
        );
    }

    private function getPlayerIdForPlanet(int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT player_id FROM planets WHERE id = :planet');
        $stmt->execute(['planet' => $planetId]);
        $playerId = $stmt->fetchColumn();

        if ($playerId === false) {
            throw new RuntimeException('Planète inconnue pour la file de recherche.');
        }

        return (int) $playerId;
    }
}
