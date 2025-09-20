<?php

namespace App\Application\UseCase\Shipyard;

use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\ShipCatalog;
use RuntimeException;

class GetShipyardOverview
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly ShipBuildQueueRepositoryInterface $shipQueue,
        private readonly FleetRepositoryInterface $fleets,
        private readonly ShipCatalog $catalog,
        private readonly ProcessShipBuildQueue $queueProcessor
    ) {
    }

    /**
     * @return array{
     *     planet: \App\Domain\Entity\Planet,
     *     shipyardLevel: int,
     *     fleet: array<string, int>,
     *     fleetSummary: array<int, array{key: string, label: string, quantity: int}>,
     *     queue: array{count: int, jobs: array<int, array{ship: string, label: string, quantity: int, endsAt: \DateTimeImmutable, remaining: int}>},
     *     categories: array<int, array{label: string, image: string, items: array<int, array<string, mixed>>}>
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
        $shipyardLevel = $buildingLevels['shipyard'] ?? 0;
        $researchLevels = $this->researchStates->getLevels($planetId);
        $catalogMap = [];
        foreach ($this->catalog->all() as $definition) {
            $catalogMap[$definition->getKey()] = ['label' => $definition->getLabel()];
        }

        $fleet = $this->fleets->getFleet($planetId);
        $fleetView = [];
        foreach ($fleet as $shipKey => $quantity) {
            $label = $catalogMap[$shipKey]['label'] ?? $shipKey;
            $fleetView[] = [
                'key' => $shipKey,
                'label' => $label,
                'quantity' => (int) $quantity,
            ];
        }
        usort($fleetView, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

        $queueJobs = $this->shipQueue->getActiveQueue($planetId);
        $queueView = [];

        foreach ($queueJobs as $job) {
            $definition = $this->catalog->get($job->getShipKey());
            $queueView[] = [
                'ship' => $job->getShipKey(),
                'label' => $definition->getLabel(),
                'quantity' => $job->getQuantity(),
                'endsAt' => $job->getEndsAt(),
                'remaining' => max(0, $job->getEndsAt()->getTimestamp() - time()),
            ];
        }

        $queueLimitReached = count($queueJobs) >= 5;

        $categories = [];
        foreach ($this->catalog->groupedByCategory() as $category => $data) {
            $items = [];
            foreach ($data['items'] as $definition) {
                $requirements = $this->checkRequirements($definition->getRequiresResearch(), $researchLevels, $catalogMap);
                $canBuild = $shipyardLevel > 0 && $requirements['ok'] && !$queueLimitReached;

                $items[] = [
                    'definition' => $definition,
                    'requirements' => $requirements,
                    'canBuild' => $canBuild,
                ];
            }

            $categories[] = [
                'label' => $category,
                'image' => $data['image'],
                'items' => $items,
            ];
        }

        return [
            'planet' => $planet,
            'shipyardLevel' => $shipyardLevel,
            'fleet' => $fleet,
            'fleetSummary' => $fleetView,
            'queue' => [
                'count' => count($queueView),
                'jobs' => $queueView,
            ],
            'categories' => $categories,
        ];
    }

    /**
     * @param array<string, int> $requirements
     * @param array<string, int> $researchLevels
     * @param array<string, array{label: string}> $catalog
     *
     * @return array{ok: bool, missing: array<int, array{type: string, key: string, label: string, level: int, current: int}>}
     */
    private function checkRequirements(array $requirements, array $researchLevels, array $catalog): array
    {
        $missing = [];

        foreach ($requirements as $key => $requiredLevel) {
            $current = (int) ($researchLevels[$key] ?? 0);
            if ($current < $requiredLevel) {
                $missing[] = [
                    'type' => 'research',
                    'key' => $key,
                    'label' => $catalog[$key]['label'] ?? $key,
                    'level' => (int) $requiredLevel,
                    'current' => $current,
                ];
            }
        }

        return [
            'ok' => empty($missing),
            'missing' => $missing,
        ];
    }
}
