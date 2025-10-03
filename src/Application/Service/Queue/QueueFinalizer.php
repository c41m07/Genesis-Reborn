<?php

declare(strict_types=1);

namespace App\Application\Service\Queue;

/**
 * Fournit un utilitaire commun pour finaliser les jobs de file d'attente avant traitement.
 */
final class QueueFinalizer
{
    /**
     * @template TJob
     *
     * @param callable(int): array<int, TJob> $finalizer Récupère et clôt les jobs éligibles.
     * @param callable(array<int, TJob>): void $onJobs Exécute la logique applicative sur les jobs.
     */
    public function finalize(int $planetId, callable $finalizer, callable $onJobs): void
    {
        $jobs = $finalizer($planetId);
        if ($jobs === []) {
            return;
        }

        $onJobs($jobs);
    }
}
