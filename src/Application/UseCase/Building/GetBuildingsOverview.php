<?php

namespace App\Application\UseCase\Building;

use App\Application\Service\ProcessBuildQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ResearchCatalog;
use InvalidArgumentException;
use RuntimeException;

class GetBuildingsOverview
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly BuildQueueRepositoryInterface $buildQueue,
        private readonly BuildingCatalog $catalog,
        private readonly BuildingCalculator $calculator,
        private readonly ProcessBuildQueue $queueProcessor,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly ResearchCatalog $researchCatalog
    ) {
    }

    /**
     * @return array{
     *     planet: \App\Domain\Entity\Planet,
     *     levels: array<string, int>,
     *     queue: array{count: int, jobs: array<int, array{building: string, label: string, targetLevel: int, endsAt: \DateTimeImmutable, remaining: int}>},
     *     buildings: array<int, array{
     *         definition: \App\Domain\Entity\BuildingDefinition,
     *         level: int,
     *         cost: array<string, int>,
     *         time: int,
     *         canUpgrade: bool,
     *         requirements: array{ok: bool, missing: array<int, array{type: string, key: string, label: string, level: int, current: int}>},
     *         production: array{resource: string, current: int, next: int, delta: int},
     *         energy: array{current: int, next: int, delta: int},
     *         storage: array{current: array<string, int>, next: array<string, int>, delta: array<string, int>}
     *     }>
     * }
     */
    public function execute(int $planetId): array
    {
        $this->queueProcessor->process($planetId);

        $planet = $this->planets->find($planetId);
        if (!$planet) {
            throw new RuntimeException('Planète introuvable.');
        }

        $levels = $this->buildingStates->getLevels($planet->getId());
        $researchLevels = $this->researchStates->getLevels($planet->getId());
        $buildings = [];
        $queueJobs = $this->buildQueue->getActiveQueue($planetId);
        $queueView = [];
        $levelsAfterQueue = $levels;

        $researchCatalogMap = [];
        foreach ($this->researchCatalog->all() as $researchDefinition) {
            $researchCatalogMap[$researchDefinition->getKey()] = [
                'label' => $researchDefinition->getLabel(),
            ];
        }

        foreach ($queueJobs as $job) {
            $definition = $this->catalog->get($job->getBuildingKey());
            $queueView[] = [
                'building' => $job->getBuildingKey(),
                'label' => $definition->getLabel(),
                'targetLevel' => $job->getTargetLevel(),
                'endsAt' => $job->getEndsAt(),
                'remaining' => max(0, $job->getEndsAt()->getTimestamp() - time()),
            ];
            $levelsAfterQueue[$job->getBuildingKey()] = $job->getTargetLevel();
        }

        $queueLimitReached = count($queueJobs) >= 5;

        foreach ($this->catalog->all() as $definition) {
            $currentLevel = $levels[$definition->getKey()] ?? 0;
            $startLevel = $levelsAfterQueue[$definition->getKey()] ?? $currentLevel;
            $nextTargetLevel = $startLevel + 1;
            $cost = $this->calculator->nextCost($definition, $startLevel);
            $time = $this->calculator->nextTime($definition, $startLevel, $levelsAfterQueue);
            $requirements = $this->calculator->checkRequirements($definition, $levelsAfterQueue, $researchLevels, $researchCatalogMap);
            if (!empty($requirements['missing'])) {
                $requirements['missing'] = array_map(function (array $missing): array {
                    if (($missing['type'] ?? '') === 'building') {
                        try {
                            $definition = $this->catalog->get($missing['key']);
                            $missing['label'] = $definition->getLabel();
                        } catch (InvalidArgumentException $exception) {
                            // Keep fallback label when definition is unknown.
                        }
                    }

                    return $missing;
                }, $requirements['missing']);
            }
            $canUpgrade = !$queueLimitReached && $requirements['ok'] && $this->canAfford($planet, $cost);

            $currentProduction = $this->calculator->productionAt($definition, $currentLevel);
            $nextProduction = $this->calculator->productionAt($definition, $nextTargetLevel);
            $currentEnergy = $this->calculator->energyUseAt($definition, $currentLevel);
            $nextEnergy = $this->calculator->energyUseAt($definition, $nextTargetLevel);
            $currentStorage = $this->calculator->storageAt($definition, $currentLevel);
            $nextStorage = $this->calculator->storageAt($definition, $nextTargetLevel);
            $storageDelta = [];
            foreach ($nextStorage as $resource => $value) {
                $storageDelta[$resource] = $value - ($currentStorage[$resource] ?? 0);
            }

            $consumption = [];
            if ($currentEnergy !== 0 || $nextEnergy !== 0) {
                $consumption['energy'] = [
                    'current' => $currentEnergy,
                    'next' => $nextEnergy,
                    'delta' => $nextEnergy - $currentEnergy,
                ];
            }

            $currentUpkeep = $this->calculator->upkeepAt($definition, $currentLevel);
            $nextUpkeep = $this->calculator->upkeepAt($definition, $nextTargetLevel);
            $upkeepResources = array_unique(array_merge(array_keys($currentUpkeep), array_keys($nextUpkeep)));
            foreach ($upkeepResources as $resource) {
                $current = $currentUpkeep[$resource] ?? 0;
                $next = $nextUpkeep[$resource] ?? 0;
                $consumption[$resource] = [
                    'current' => $current,
                    'next' => $next,
                    'delta' => $next - $current,
                ];
            }

            $buildings[] = [
                'definition' => $definition,
                'level' => $currentLevel,
                'cost' => $cost,
                'time' => $time,
                'requirements' => $requirements,
                'canUpgrade' => $canUpgrade,
                'production' => [
                    'resource' => $definition->getAffects(),
                    'current' => $currentProduction,
                    'next' => $nextProduction,
                    'delta' => $nextProduction - $currentProduction,
                ],
                'consumption' => $consumption,
                'storage' => [
                    'current' => $currentStorage,
                    'next' => $nextStorage,
                    'delta' => $storageDelta,
                ],
            ];
        }

        return [
            'planet' => $planet,
            'levels' => $levels,
            'queue' => [
                'count' => count($queueView),
                'jobs' => $queueView,
            ],
            'buildings' => $buildings,
        ];
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
}
