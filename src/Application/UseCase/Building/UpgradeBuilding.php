<?php

namespace App\Application\UseCase\Building;

use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;

class UpgradeBuilding
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly BuildQueueRepositoryInterface $buildQueue,
        private readonly BuildingCatalog $catalog,
        private readonly BuildingCalculator $calculator
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

        $requirements = $this->calculator->checkRequirements($definition, $levels, []);
        if (!$requirements['ok']) {
            return ['success' => false, 'message' => 'Pré-requis manquants.'];
        }

        $cost = $this->calculator->nextCost($definition, $currentLevel);
        if (!$this->canAfford($planet, $cost)) {
            return ['success' => false, 'message' => 'Ressources insuffisantes.'];
        }

        $this->deductCost($planet, $cost);

        $duration = $this->calculator->nextTime($definition, $currentLevel);
        $this->buildQueue->enqueue($planetId, $buildingKey, $currentLevel + 1, $duration);
        $this->planets->update($planet);

        return ['success' => true, 'message' => 'Construction planifiée.'];
    }

    /**
     * @param array<string, int> $cost
     */
    private function canAfford(\App\Domain\Entity\Planet $planet, array $cost): bool
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
    private function deductCost(\App\Domain\Entity\Planet $planet, array $cost): void
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

}
