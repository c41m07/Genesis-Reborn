<?php

namespace App\Application\UseCase\Research;

use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Service\ResearchCalculator;
use App\Domain\Service\ResearchCatalog;

class StartResearch
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly ResearchQueueRepositoryInterface $researchQueue,
        private readonly ResearchCatalog $catalog,
        private readonly ResearchCalculator $calculator
    ) {
    }

    /** @return array{success: bool, message?: string} */
    public function execute(int $planetId, int $userId, string $researchKey): array
    {
        $planet = $this->planets->find($planetId);
        if (!$planet || $planet->getUserId() !== $userId) {
            return ['success' => false, 'message' => 'Action non autorisée.'];
        }

        $definition = $this->catalog->get($researchKey);
        $buildingLevels = $this->buildingStates->getLevels($planetId);
        $researchLevels = $this->researchStates->getLevels($planetId);
        $currentLevel = $researchLevels[$researchKey] ?? 0;

        if ($currentLevel >= $definition->getMaxLevel()) {
            return ['success' => false, 'message' => 'Ce domaine scientifique a atteint son niveau maximal.'];
        }

        $labLevel = $buildingLevels['research_lab'] ?? 0;
        $catalogMap = [];
        foreach ($this->catalog->all() as $def) {
            $catalogMap[$def->getKey()] = ['label' => $def->getLabel()];
        }

        $requirements = $this->calculator->checkRequirements($definition, $researchLevels, $labLevel, $catalogMap);
        if (!$requirements['ok']) {
            return ['success' => false, 'message' => 'Pré-requis de recherche manquants.'];
        }

        $cost = $this->calculator->nextCost($definition, $currentLevel);
        if (!$this->canAfford($planet, $cost)) {
            return ['success' => false, 'message' => 'Ressources insuffisantes pour lancer cette recherche.'];
        }

        $this->deductCost($planet, $cost);
        $duration = $this->calculator->nextTime($definition, $currentLevel);
        $this->researchQueue->enqueue($planetId, $researchKey, $currentLevel + 1, $duration);
        $this->planets->update($planet);

        return ['success' => true, 'message' => 'Recherche planifiée.'];
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
