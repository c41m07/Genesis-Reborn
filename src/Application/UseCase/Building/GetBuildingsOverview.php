<?php

namespace App\Application\UseCase\Building;

use App\Application\Service\ProcessBuildQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use RuntimeException;

class GetBuildingsOverview
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly BuildQueueRepositoryInterface $buildQueue,
        private readonly BuildingCatalog $catalog,
        private readonly BuildingCalculator $calculator,
        private readonly ProcessBuildQueue $queueProcessor
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
     *         energy: array{current: int, next: int, delta: int}
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
        $buildings = [];
        $queueJobs = $this->buildQueue->getActiveQueue($planetId);
        $queueView = [];
        $queuedByBuilding = [];

        foreach ($queueJobs as $job) {
            $definition = $this->catalog->get($job->getBuildingKey());
            $queueView[] = [
                'building' => $job->getBuildingKey(),
                'label' => $definition->getLabel(),
                'targetLevel' => $job->getTargetLevel(),
                'endsAt' => $job->getEndsAt(),
                'remaining' => max(0, $job->getEndsAt()->getTimestamp() - time()),
            ];
            $queuedByBuilding[$job->getBuildingKey()] = ($queuedByBuilding[$job->getBuildingKey()] ?? 0) + 1;
        }

        $queueLimitReached = count($queueJobs) >= 5;

        foreach ($this->catalog->all() as $definition) {
            $currentLevel = $levels[$definition->getKey()] ?? 0;
            $queuedCount = $queuedByBuilding[$definition->getKey()] ?? 0;
            $effectiveLevel = $currentLevel + $queuedCount;
            $nextTargetLevel = $effectiveLevel + 1;
            $cost = $this->calculator->nextCost($definition, $effectiveLevel);
            $time = $this->calculator->nextTime($definition, $effectiveLevel);
            $requirements = $this->calculator->checkRequirements($definition, $levels, []);
            $canUpgrade = !$queueLimitReached && $requirements['ok'] && $this->canAfford($planet, $cost);

            $currentProduction = $this->calculator->productionAt($definition, $currentLevel);
            $nextProduction = $this->calculator->productionAt($definition, $nextTargetLevel);
            $currentEnergy = $this->calculator->energyUseAt($definition, $currentLevel);
            $nextEnergy = $this->calculator->energyUseAt($definition, $nextTargetLevel);

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
                'energy' => [
                    'current' => $currentEnergy,
                    'next' => $nextEnergy,
                    'delta' => $nextEnergy - $currentEnergy,
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
