<?php

namespace App\Application\Service;

use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;

class ProcessBuildQueue
{
    public function __construct(
        private readonly BuildQueueRepositoryInterface $queue,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingCatalog $catalog,
        private readonly BuildingCalculator $calculator
    ) {
    }

    public function process(int $planetId): void
    {
        $jobs = $this->queue->finalizeDueJobs($planetId);
        if ($jobs === []) {
            return;
        }

        $planet = $this->planets->find($planetId);
        if (!$planet) {
            return;
        }

        $levels = $this->buildingStates->getLevels($planetId);

        foreach ($jobs as $job) {
            $levels[$job->getBuildingKey()] = $job->getTargetLevel();
            $this->buildingStates->setLevel($planetId, $job->getBuildingKey(), $job->getTargetLevel());
        }

        $production = $this->computeProduction($levels);

        $planet->setMetalPerHour($production['metal']);
        $planet->setCrystalPerHour($production['crystal']);
        $planet->setHydrogenPerHour($production['hydrogen']);
        $planet->setEnergyPerHour($production['energyProduction'] - $production['energyConsumption']);

        $this->planets->update($planet);
    }

    /**
     * @param array<string, int> $levels
     *
     * @return array{metal: int, crystal: int, hydrogen: int, energyProduction: int, energyConsumption: int}
     */
    private function computeProduction(array $levels): array
    {
        $production = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energyProduction' => 0, 'energyConsumption' => 0];

        foreach ($this->catalog->all() as $definition) {
            $level = $levels[$definition->getKey()] ?? 0;
            $prod = $this->calculator->productionAt($definition, $level);
            $energyUse = $this->calculator->energyUseAt($definition, $level);

            switch ($definition->getAffects()) {
                case 'metal':
                    $production['metal'] += $prod;
                    $production['energyConsumption'] += $energyUse;
                    break;
                case 'crystal':
                    $production['crystal'] += $prod;
                    $production['energyConsumption'] += $energyUse;
                    break;
                case 'hydrogen':
                    $production['hydrogen'] += $prod;
                    $production['energyConsumption'] += $energyUse;
                    break;
                case 'energy':
                    $production['energyProduction'] += $prod;
                    $production['energyConsumption'] += $energyUse;
                    break;
            }
        }

        return $production;
    }
}
