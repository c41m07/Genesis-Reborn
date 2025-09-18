<?php

namespace App\Application\UseCase\Shipyard;

use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\ShipCatalog;

class BuildShips
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly ShipBuildQueueRepositoryInterface $shipQueue,
        private readonly ShipCatalog $catalog
    ) {
    }

    /** @return array{success: bool, message?: string} */
    public function execute(int $planetId, int $userId, string $shipKey, int $quantity): array
    {
        $planet = $this->planets->find($planetId);
        if (!$planet || $planet->getUserId() !== $userId) {
            return ['success' => false, 'message' => 'Action non autorisée.'];
        }

        $definition = $this->catalog->get($shipKey);
        $buildingLevels = $this->buildingStates->getLevels($planetId);
        $shipyardLevel = $buildingLevels['shipyard'] ?? 0;

        if ($shipyardLevel <= 0) {
            return ['success' => false, 'message' => 'Aucun chantier spatial opérationnel.'];
        }

        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Quantité invalide.'];
        }

        $researchLevels = $this->researchStates->getLevels($planetId);
        foreach ($definition->getRequiresResearch() as $key => $level) {
            $current = (int) ($researchLevels[$key] ?? 0);
            if ($current < $level) {
                return ['success' => false, 'message' => 'Recherches insuffisantes pour ce modèle.'];
            }
        }

        $cost = $this->multiplyCost($definition->getBaseCost(), $quantity);
        if (!$this->canAfford($planet, $cost)) {
            return ['success' => false, 'message' => 'Ressources insuffisantes pour construire ces vaisseaux.'];
        }

        $this->deductCost($planet, $cost);
        $duration = max(0, $definition->getBuildTime() * $quantity);
        $this->shipQueue->enqueue($planetId, $shipKey, $quantity, $duration);
        $this->planets->update($planet);

        return ['success' => true, 'message' => 'Production planifiée.'];
    }

    /**
     * @param array<string, int> $baseCost
     *
     * @return array<string, int>
     */
    private function multiplyCost(array $baseCost, int $quantity): array
    {
        $costs = [];
        foreach ($baseCost as $resource => $amount) {
            $costs[$resource] = (int) ($amount * $quantity);
        }

        return $costs;
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
