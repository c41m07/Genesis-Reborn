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
    /** @var array<string, array{label: string, order: int, image: string|null}> */
    private const CATEGORY_METADATA = [
        'production' => ['label' => 'Production', 'order' => 0, 'image' => null],
        'energy' => ['label' => 'Énergie', 'order' => 1, 'image' => null],
        'science' => ['label' => 'Recherche', 'order' => 2, 'image' => null],
        'military' => ['label' => 'Militaire', 'order' => 3, 'image' => null],
        'infrastructure' => ['label' => 'Infrastructure', 'order' => 9, 'image' => null],
    ];

    /** @var array<string, string> */
    private const BUILDING_CATEGORY_MAP = [
        'metal_mine' => 'production',
        'crystal_mine' => 'production',
        'hydrogen_plant' => 'production',
        'solar_plant' => 'energy',
        'fusion_reactor' => 'energy',
        'antimatter_reactor' => 'energy',
        'research_lab' => 'science',
        'shipyard' => 'military',
        'storage_depot' => 'infrastructure',
        'worker_factory' => 'infrastructure',
        'robot_factory' => 'infrastructure',
    ];

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
     *     workerFactory: array{level: int, bonus: float},
     *     robotFactory: array{level: int, bonus: float},
     *     queue: array{count: int, limit: int, jobs: array<int, array{building: string, label: string, targetLevel: int, endsAt: \DateTimeImmutable, remaining: int}>},
     *     buildings: array<int, array{
     *         definition: \App\Domain\Entity\BuildingDefinition,
     *         level: int,
     *         cost: array<string, int>,
     *         time: int,
     *         canUpgrade: bool,
     *         requirements: array{ok: bool, missing: array<int, array{type: string, key: string, label: string, level: int, current: int}>},
     *         production: array{resource: string, current: int, next: int, delta: int},
     *         energy: array{current: int, next: int, delta: int},
     *         storage: array{current: array<string, int>, next: array<string, int>, delta: array<string, int>},
     *         bonuses: array<string, mixed>
     *     }>,
     *     categories: array<int, array{key: string, label: string, image: string|null, items: array<int, array<string, mixed>>}>
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
        $workerFactoryLevel = (int) ($levels['worker_factory'] ?? 0);
        $robotFactoryLevel = (int) ($levels['robot_factory'] ?? 0);
        $workerFactoryBonus = 0.0;
        $robotFactoryBonus = 0.0;

        try {
            $workerFactoryDefinition = $this->catalog->get('worker_factory');
            $workerFactoryBonus = $this->calculator->constructionSpeedBonusAt($workerFactoryDefinition, $workerFactoryLevel);
        } catch (InvalidArgumentException) {
            $workerFactoryBonus = 0.0;
        }

        try {
            $robotFactoryDefinition = $this->catalog->get('robot_factory');
            $robotFactoryBonus = $this->calculator->constructionSpeedBonusAt($robotFactoryDefinition, $robotFactoryLevel);
        } catch (InvalidArgumentException) {
            $robotFactoryBonus = 0.0;
        }

        $researchLevels = $this->researchStates->getLevels($planet->getId());
        $buildings = [];
        $queueJobs = $this->buildQueue->getActiveQueue($planetId);
        $queueView = [];

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
        }

        $queueLimitReached = count($queueJobs) >= 5;

        $projectedLevels = $levels;
        foreach ($queueJobs as $job) {
            $key = $job->getBuildingKey();
            $projectedLevels[$key] = ($projectedLevels[$key] ?? 0) + 1;
        }

        foreach ($this->catalog->all() as $definition) {
            $currentLevel = $levels[$definition->getKey()] ?? 0;
            $effectiveLevel = $projectedLevels[$definition->getKey()] ?? $currentLevel;
            $nextTargetLevel = $effectiveLevel + 1;
            $cost = $this->calculator->nextCost($definition, $effectiveLevel);
            $baseTime = $this->calculator->nextTime($definition, $effectiveLevel);
            $time = $this->calculator->nextTime($definition, $effectiveLevel, $projectedLevels);
            $requirements = $this->calculator->checkRequirements($definition, $levels, $researchLevels, $researchCatalogMap);
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

            $bonuses = [];
            $constructionCurrent = $this->calculator->constructionSpeedBonusAt($definition, $currentLevel);
            $constructionNext = $this->calculator->constructionSpeedBonusAt($definition, $nextTargetLevel);
            if ($constructionCurrent > 0.0 || $constructionNext > 0.0) {
                $bonuses['construction_speed'] = [
                    'current' => $constructionCurrent,
                    'next' => $constructionNext,
                    'delta' => max(0.0, $constructionNext - $constructionCurrent),
                ];
            }

            $researchSpeedCurrent = $this->calculator->researchSpeedBonusAt($definition, $currentLevel);
            $researchSpeedNext = $this->calculator->researchSpeedBonusAt($definition, $nextTargetLevel);
            if ($researchSpeedCurrent > 0.0 || $researchSpeedNext > 0.0) {
                $bonuses['research_speed'] = [
                    'current' => $researchSpeedCurrent,
                    'next' => $researchSpeedNext,
                    'delta' => max(0.0, $researchSpeedNext - $researchSpeedCurrent),
                ];
            }

            $shipSpeedCurrent = $this->calculator->shipBuildSpeedBonus($definition, $currentLevel);
            $shipSpeedNext = $this->calculator->shipBuildSpeedBonus($definition, $nextTargetLevel);
            if ($shipSpeedCurrent > 0.0 || $shipSpeedNext > 0.0) {
                $bonuses['ship_build_speed'] = [
                    'current' => $shipSpeedCurrent,
                    'next' => $shipSpeedNext,
                    'delta' => max(0.0, $shipSpeedNext - $shipSpeedCurrent),
                ];
            }

            $buildings[] = [
                'definition' => $definition,
                'level' => $currentLevel,
                'cost' => $cost,
                'time' => $time,
                'baseTime' => $baseTime,
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
                'bonuses' => $bonuses,
            ];
        }

        $categories = [];
        foreach ($buildings as $entry) {
            $definition = $entry['definition'];
            $categoryKey = self::BUILDING_CATEGORY_MAP[$definition->getKey()] ?? 'infrastructure';
            $metadata = self::CATEGORY_METADATA[$categoryKey] ?? self::CATEGORY_METADATA['infrastructure'];

            if (!isset($categories[$categoryKey])) {
                $categories[$categoryKey] = [
                    'key' => $categoryKey,
                    'label' => $metadata['label'],
                    'image' => $metadata['image'],
                    'order' => $metadata['order'],
                    'items' => [],
                ];
            }

            $categories[$categoryKey]['items'][] = $entry;
        }
        uasort($categories, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return [
            'planet' => $planet,
            'levels' => $levels,
            'workerFactory' => [
                'level' => $workerFactoryLevel,
                'bonus' => $workerFactoryBonus,
            ],
            'robotFactory' => [
                'level' => $robotFactoryLevel,
                'bonus' => $robotFactoryBonus,
            ],
            'queue' => [
                'count' => count($queueView),
                'limit' => 5,
                'jobs' => $queueView,
            ],
            'buildings' => $buildings,
            'categories' => array_values($categories),
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
