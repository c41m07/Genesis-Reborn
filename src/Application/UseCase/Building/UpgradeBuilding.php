<?php

declare(strict_types=1);

namespace App\Application\UseCase\Building;

use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\PlayerStatsRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;

class UpgradeBuilding
{
    public function __construct(
        private readonly PlanetRepositoryInterface        $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly BuildQueueRepositoryInterface    $buildQueue,
        private readonly PlayerStatsRepositoryInterface   $playerStats,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly BuildingCatalog                  $catalog,
        private readonly BuildingCalculator               $calculator
    ) {
    }

    /** @return array{success: bool, message?: string} */
    public function execute(int $planetId, int $userId, string $buildingKey): array
    {
        $planet = $this->planets->find($planetId);
        if (!$planet || $planet->getUserId() !== $userId) {
            return ['success' => false, 'message' => 'Action non autorisée.'];
        }

        $definition = $this->catalog->get($buildingKey);
        $levels = $this->buildingStates->getLevels($planetId);
        $currentLevel = $levels[$buildingKey] ?? 0;

        if ($this->buildQueue->countActive($planetId) >= 5) {
            return ['success' => false, 'message' => 'La file de construction est pleine (5 actions maximum).'];
        }

        $existingJobs = $this->buildQueue->getActiveQueue($planetId);
        $projectedLevels = $levels;
        foreach ($existingJobs as $job) {
            $key = $job->getBuildingKey();
            $projectedLevels[$key] = ($projectedLevels[$key] ?? 0) + 1;
        }

        $baseLevelForUpgrade = $projectedLevels[$buildingKey] ?? $currentLevel;
        $targetLevel = $baseLevelForUpgrade + 1;

        $researchLevels = $this->researchStates->getLevels($planetId);
        $requirements = $this->calculator->checkRequirements($definition, $levels, $researchLevels);
        if (!$requirements['ok']) {
            return ['success' => false, 'message' => 'Pré-requis manquants.'];
        }

        $cost = $this->calculator->nextCost($definition, $targetLevel - 1);
        if (!$this->canAfford($planet, $cost)) {
            return ['success' => false, 'message' => 'Ressources insuffisantes.'];
        }

        $this->deductCost($planet, $cost);

        $duration = $this->calculator->nextTime($definition, $targetLevel - 1, $projectedLevels);
        $this->buildQueue->enqueue($planetId, $buildingKey, $targetLevel, $duration);
        $this->playerStats->addBuildingSpending($userId, $this->sumCost($cost));
        $this->planets->update($planet);

        return ['success' => true, 'message' => 'Construction planifiée.'];
    }

    /**
     * @param array<string, int> $cost
     */
    private function canAfford(Planet $planet, array $cost): bool
    {
        foreach ($cost as $resource => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $current = match ($resource) {
                'metal' => $planet->getMetal(),
                'crystal' => $planet->getCrystal(),
                'hydrogen' => $planet->getHydrogen(),
                default => null,
            };

            if ($current === null || $current < $amount) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, int> $cost
     */
    private function deductCost(Planet $planet, array $cost): void
    {
        foreach ($cost as $resource => $amount) {
            if ($amount <= 0) {
                continue;
            }

            switch ($resource) {
                case 'metal':
                    $planet->setMetal(max(0, $planet->getMetal() - $amount));
                    break;
                case 'crystal':
                    $planet->setCrystal(max(0, $planet->getCrystal() - $amount));
                    break;
                case 'hydrogen':
                    $planet->setHydrogen(max(0, $planet->getHydrogen() - $amount));
                    break;
            }
        }
    }

    /**
     * @param array<string, int> $cost
     */
    private function sumCost(array $cost): int
    {
        $total = 0;
        foreach ($cost as $amount) {
            if ($amount > 0) {
                $total += (int)$amount;
            }
        }

        return $total;
    }
}
