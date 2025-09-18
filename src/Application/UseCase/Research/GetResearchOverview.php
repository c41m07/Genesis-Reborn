<?php

namespace App\Application\UseCase\Research;

use App\Application\Service\ProcessResearchQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Service\ResearchCalculator;
use App\Domain\Service\ResearchCatalog;
use RuntimeException;

class GetResearchOverview
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly ResearchQueueRepositoryInterface $researchQueue,
        private readonly ResearchCatalog $catalog,
        private readonly ResearchCalculator $calculator,
        private readonly ProcessResearchQueue $queueProcessor
    ) {
    }

    /**
     * @return array{
     *     planet: \App\Domain\Entity\Planet,
     *     labLevel: int,
     *     researchLevels: array<string, int>,
     *     queue: array{count: int, jobs: array<int, array{research: string, label: string, targetLevel: int, endsAt: \DateTimeImmutable, remaining: int}>},
     *     categories: array<int, array{label: string, image: string, items: array<int, array{definition: \App\Domain\Entity\ResearchDefinition, level: int, maxLevel: int, progress: float, nextCost: array<string, int>, nextTime: int, requirements: array{ok: bool, missing: array<int, array{type: string, key: string, label: string, level: int, current: int}>}, canResearch: bool}>>,
     *     totals: array{completedLevels: int, unlockedResearch: int, highestLevel: int}
     * }
     */
    public function execute(int $planetId): array
    {
        $this->queueProcessor->process($planetId);

        $planet = $this->planets->find($planetId);
        if (!$planet) {
            throw new RuntimeException('Planète introuvable.');
        }

        $buildingLevels = $this->buildingStates->getLevels($planetId);
        $researchLevels = $this->researchStates->getLevels($planetId);
        $labLevel = $buildingLevels['research_lab'] ?? 0;

        $catalogMap = [];
        foreach ($this->catalog->all() as $definition) {
            $catalogMap[$definition->getKey()] = ['label' => $definition->getLabel()];
        }

        $queueJobs = $this->researchQueue->getActiveQueue($planetId);
        $queueView = [];

        foreach ($queueJobs as $job) {
            $definition = $this->catalog->get($job->getResearchKey());
            $queueView[] = [
                'research' => $job->getResearchKey(),
                'label' => $definition->getLabel(),
                'targetLevel' => $job->getTargetLevel(),
                'endsAt' => $job->getEndsAt(),
                'remaining' => max(0, $job->getEndsAt()->getTimestamp() - time()),
            ];
        }

        $categories = [];
        foreach ($this->catalog->groupedByCategory() as $category => $data) {
            $items = [];
            foreach ($data['items'] as $definition) {
                $currentLevel = $researchLevels[$definition->getKey()] ?? 0;
                $nextCost = $this->calculator->nextCost($definition, $currentLevel);
                $nextTime = $this->calculator->nextTime($definition, $currentLevel);
                $requirements = $this->calculator->checkRequirements(
                    $definition,
                    $researchLevels,
                    $labLevel,
                    $catalogMap
                );

                $canResearch = $currentLevel < $definition->getMaxLevel()
                    && $requirements['ok']
                    && $this->canAfford($planet->getMetal(), $planet->getCrystal(), $planet->getHydrogen(), $nextCost);

                $items[] = [
                    'definition' => $definition,
                    'level' => $currentLevel,
                    'maxLevel' => $definition->getMaxLevel(),
                    'progress' => $definition->getMaxLevel() > 0 ? min(1.0, $currentLevel / $definition->getMaxLevel()) : 0,
                    'nextCost' => $nextCost,
                    'nextTime' => $nextTime,
                    'requirements' => $requirements,
                    'canResearch' => $canResearch,
                ];
            }

            $categories[] = [
                'label' => $category,
                'image' => $data['image'],
                'items' => $items,
            ];
        }

        $completedLevels = array_sum($researchLevels);
        $unlockedResearch = count(array_filter($researchLevels, static fn (int $level): bool => $level > 0));
        $highestLevel = empty($researchLevels) ? 0 : max($researchLevels);

        return [
            'planet' => $planet,
            'labLevel' => $labLevel,
            'researchLevels' => $researchLevels,
            'queue' => [
                'count' => count($queueView),
                'jobs' => $queueView,
            ],
            'categories' => $categories,
            'totals' => [
                'completedLevels' => $completedLevels,
                'unlockedResearch' => $unlockedResearch,
                'highestLevel' => $highestLevel,
            ],
        ];
    }

    /**
     * @param array<string, int> $cost
     */
    private function canAfford(int $metal, int $crystal, int $hydrogen, array $cost): bool
    {
        foreach ($cost as $resource => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $current = match ($resource) {
                'metal' => $metal,
                'crystal' => $crystal,
                'hydrogen' => $hydrogen,
                default => null,
            };

            if ($current === null || $current < $amount) {
                return false;
            }
        }

        return true;
    }
}
