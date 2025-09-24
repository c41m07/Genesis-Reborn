<?php

namespace App\Application\UseCase\Shipyard;

use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Entity\BuildingDefinition;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ShipCatalog;
use RuntimeException;

class GetShipyardOverview
{
    private readonly BuildingDefinition $shipyardDefinition;

    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly ShipBuildQueueRepositoryInterface $shipQueue,
        private readonly FleetRepositoryInterface $fleets,
        private readonly ShipCatalog $catalog,
        private readonly ProcessShipBuildQueue $queueProcessor,
        BuildingCatalog $buildingCatalog,
        private readonly BuildingCalculator $buildingCalculator
    ) {
        $this->shipyardDefinition = $buildingCatalog->get('shipyard');
    }

    /**
     * @return array{
     *     planet: \App\Domain\Entity\Planet,
     *     shipyardLevel: int,
     *     fleet: array<string, int>,
     *     fleetSummary: array<int, array{key: string, label: string, quantity: int}>,
     *     queue: array{count: int, jobs: array<int, array{ship: string, label: string, quantity: int, endsAt: \DateTimeImmutable, remaining: int}>},
     *     categories: array<int, array{
     *         label: string,
     *         image: string,
     *         items: array<int, array{
     *             definition: \App\Domain\Entity\ShipDefinition,
     *             requirements: array{ok: bool, missing: array<int, array{type: string, key: string, label: string, level: int, current: int}>},
     *             canBuild: bool,
     *             buildTime: int,
     *             baseBuildTime: int,
     *             affordable: bool,
     *         }>
     *     }>,
     *     shipyardBonus: float
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
        $shipyardBonus = $this->buildingCalculator->shipBuildSpeedBonus($this->shipyardDefinition, $shipyardLevel);
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
                $cost = $definition->getBaseCost();
                $isAffordable = $this->canAfford($planet, $cost);
                $canBuild = $shipyardLevel > 0 && $requirements['ok'] && !$queueLimitReached && $isAffordable;

                $buildTime = $this->buildingCalculator->applyShipBuildSpeedBonus(
                    $this->shipyardDefinition,
                    $shipyardLevel,
                    $definition->getBuildTime()
                );

                $items[] = [
                    'definition' => $definition,
                    'requirements' => $requirements,
                    'canBuild' => $canBuild,
                    'buildTime' => $buildTime,
                    'baseBuildTime' => $definition->getBuildTime(),
                    'affordable' => $isAffordable,
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
            'buildingLevels' => $buildingLevels,
            'fleet' => $fleet,
            'fleetSummary' => $fleetView,
            'queue' => [
                'count' => count($queueView),
                'jobs' => $queueView,
            ],
            'categories' => $categories,
            'shipyardBonus' => $shipyardBonus,
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
