<?php

declare(strict_types=1);

namespace App\Application\UseCase\Dashboard;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\PlayerStatsRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ShipCatalog;
use InvalidArgumentException;

class GetDashboard
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly BuildQueueRepositoryInterface $buildQueue,
        private readonly ResearchQueueRepositoryInterface $researchQueue,
        private readonly ShipBuildQueueRepositoryInterface $shipQueue,
        private readonly PlayerStatsRepositoryInterface $playerStats,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly FleetRepositoryInterface $fleets,
        private readonly BuildingCatalog $catalog,
        private readonly ResearchCatalog $researchCatalog,
        private readonly ShipCatalog $shipCatalog,
        private readonly BuildingCalculator $calculator,
        private readonly ProcessBuildQueue $processBuildQueue,
        private readonly ProcessResearchQueue $processResearchQueue,
        private readonly ProcessShipBuildQueue $processShipQueue
    ) {
    }

    /**
     * @return array{
     *     planets: array<int, array{
     *         planet: \App\Domain\Entity\Planet,
     *         levels: array<string, int>,
     *         production: array{metal: int, crystal: int, hydrogen: int, energy: int},
     *         energyBalance: array{production: int, consumption: int, net: int},
     *         queues: array{
     *             buildings: array{count: int, next?: array{building: string, label: string, targetLevel: int, endsAt: \DateTimeImmutable, remaining: int}|null},
     *             research: array{count: int, next?: array{research: string, label: string, targetLevel: int, endsAt: \DateTimeImmutable, remaining: int}|null},
     *             shipyard: array{count: int, next?: array{ship: string, label: string, quantity: int, endsAt: \DateTimeImmutable, remaining: int}|null}
     *         }
     *     }>,
     *     totals: array{metal: int, crystal: int, hydrogen: int, energy: int}
     * }
     */
    public function execute(int $userId): array
    {
        $planets = $this->planets->findByUser($userId);
        $planetSummaries = [];
        $totals = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0];
        $buildingPoints = 0;
        $sciencePoints = 0;
        $researchSum = 0;
        $unlockedResearch = 0;
        $highestTech = ['label' => 'Aucune technologie', 'level' => 0];
        $militaryPower = 0;

        foreach ($planets as $planet) {
            $planetId = $planet->getId();

            $this->processBuildQueue->process($planetId);
            $this->processResearchQueue->process($planetId);
            $this->processShipQueue->process($planetId);

            $planet = $this->planets->find($planetId) ?? $planet;
            $levels = $this->buildingStates->getLevels($planetId);
            $researchLevels = $this->researchStates->getLevels($planetId);
            $production = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0];
            $energyConsumption = 0;

            foreach ($this->catalog->all() as $definition) {
                $level = $levels[$definition->getKey()] ?? 0;
                $prod = $this->calculator->productionAt($definition, $level);
                $energyUse = $this->calculator->energyUseAt($definition, $level);

                switch ($definition->getAffects()) {
                    case 'metal':
                        $production['metal'] += $prod;
                        $energyConsumption += $energyUse;
                        break;
                    case 'crystal':
                        $production['crystal'] += $prod;
                        $energyConsumption += $energyUse;
                        break;
                    case 'hydrogen':
                        $production['hydrogen'] += $prod;
                        $energyConsumption += $energyUse;
                        break;
                    case 'energy':
                        $production['energy'] += $prod;
                        $energyConsumption += $energyUse;
                        break;
                }
            }

            $totals['metal'] += $planet->getMetal();
            $totals['crystal'] += $planet->getCrystal();
            $totals['hydrogen'] += $planet->getHydrogen();
            $totals['energy'] += $planet->getEnergy();

            $buildingTotal = array_sum($levels);
            $researchTotal = array_sum($researchLevels);

            $buildingPoints += $buildingTotal;
            $sciencePoints += $researchTotal;

            $researchSum += $researchTotal;
            $unlockedResearch += count(array_filter($researchLevels, static fn (int $level): bool => $level > 0));
            foreach ($researchLevels as $researchKey => $researchLevel) {
                if ($researchLevel <= $highestTech['level']) {
                    continue;
                }

                try {
                    $definition = $this->researchCatalog->get($researchKey);
                } catch (InvalidArgumentException $exception) {
                    // Configuration catalogue manquante pour cette technologie – on ignore l'entrée.
                    continue;
                }

                $highestTech = [
                    'label' => $definition->getLabel(),
                    'level' => $researchLevel,
                ];
            }

            $buildingQueue = $this->buildQueue->getActiveQueue($planetId);
            $researchQueue = $this->researchQueue->getActiveQueue($planetId);
            $shipQueue = $this->shipQueue->getActiveQueue($planetId);
            $fleet = $this->fleets->getFleet($planetId);
            $fleetPower = 0;
            $fleetView = [];
            foreach ($fleet as $shipKey => $quantity) {
                try {
                    $shipDefinition = $this->shipCatalog->get($shipKey);
                } catch (\InvalidArgumentException $exception) {
                    continue;
                }
                $stats = $shipDefinition->getStats();
                $attack = $stats['attaque'] ?? 0;
                $defense = $stats['défense'] ?? 0;
                $fleetPower += ($attack + $defense) * (int) $quantity;
                $fleetView[] = [
                    'key' => $shipKey,
                    'label' => $shipDefinition->getLabel(),
                    'quantity' => (int) $quantity,
                ];
            }
            usort($fleetView, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);
            $militaryPower += $fleetPower;

            $planetSummaries[] = [
                'planet' => $planet,
                'levels' => $levels,
                'researchLevels' => $researchLevels,
                'production' => $production,
                'energyBalance' => [
                    'production' => $production['energy'],
                    'consumption' => $energyConsumption,
                    'net' => $production['energy'] - $energyConsumption,
                ],
                'queues' => [
                    'buildings' => $this->summarizeBuildingQueue($buildingQueue),
                    'research' => $this->summarizeResearchQueue($researchQueue),
                    'shipyard' => $this->summarizeShipQueue($shipQueue),
                ],
                'fleet' => [
                    'ships' => $fleetView,
                    'power' => $fleetPower,
                ],
            ];
        }

        $scienceSpent = $this->playerStats->getScienceSpending($userId);
        $sciencePower = (int) floor($scienceSpent / 1000);

        $empireScore = $buildingPoints + $sciencePoints + $militaryPower;

        return [
            'planets' => $planetSummaries,
            'totals' => $totals,
            'researchTotals' => [
                'sumLevels' => $researchSum,
                'unlocked' => $unlockedResearch,
                'best' => $highestTech,
            ],
            'empire' => [
                'points' => $empireScore,
                'buildingPoints' => $buildingPoints,
                'sciencePoints' => $sciencePoints,
                'militaryPoints' => $militaryPower,
                'militaryPower' => $militaryPower,
                'planetCount' => count($planets),
                'scienceSpent' => $scienceSpent,
                'sciencePower' => $sciencePower,
            ],
        ];
    }

    /**
     * @param array<int, \App\Domain\Entity\BuildJob> $jobs
     *
     * @return array{count: int, next?: array{building: string, label: string, targetLevel: int, endsAt: \DateTimeImmutable, remaining: int}|null}
     */
    private function summarizeBuildingQueue(array $jobs): array
    {
        $summary = ['count' => count($jobs)];
        if ($jobs === []) {
            $summary['next'] = null;

            return $summary;
        }

        usort($jobs, static fn ($a, $b) => $a->getEndsAt() <=> $b->getEndsAt());
        $job = $jobs[0];
        $definition = $this->catalog->get($job->getBuildingKey());
        $summary['next'] = [
            'building' => $job->getBuildingKey(),
            'label' => $definition->getLabel(),
            'targetLevel' => $job->getTargetLevel(),
            'endsAt' => $job->getEndsAt(),
            'remaining' => max(0, $job->getEndsAt()->getTimestamp() - time()),
        ];

        return $summary;
    }

    /**
     * @param array<int, \App\Domain\Entity\ResearchJob> $jobs
     *
     * @return array{count: int, next?: array{research: string, label: string, targetLevel: int, endsAt: \DateTimeImmutable, remaining: int}|null}
     */
    private function summarizeResearchQueue(array $jobs): array
    {
        $summary = ['count' => count($jobs)];
        if ($jobs === []) {
            $summary['next'] = null;

            return $summary;
        }

        usort($jobs, static fn ($a, $b) => $a->getEndsAt() <=> $b->getEndsAt());
        $job = $jobs[0];

        try {
            $definition = $this->researchCatalog->get($job->getResearchKey());
            $label = $definition->getLabel();
        } catch (InvalidArgumentException $exception) {
            $label = $job->getResearchKey();
        }

        $summary['next'] = [
            'research' => $job->getResearchKey(),
            'label' => $label,
            'targetLevel' => $job->getTargetLevel(),
            'endsAt' => $job->getEndsAt(),
            'remaining' => max(0, $job->getEndsAt()->getTimestamp() - time()),
        ];

        return $summary;
    }

    /**
     * @param array<int, \App\Domain\Entity\ShipBuildJob> $jobs
     *
     * @return array{count: int, next?: array{ship: string, label: string, quantity: int, endsAt: \DateTimeImmutable, remaining: int}|null}
     */
    private function summarizeShipQueue(array $jobs): array
    {
        $summary = ['count' => count($jobs)];
        if ($jobs === []) {
            $summary['next'] = null;

            return $summary;
        }

        usort($jobs, static fn ($a, $b) => $a->getEndsAt() <=> $b->getEndsAt());
        $job = $jobs[0];
        $definition = $this->shipCatalog->get($job->getShipKey());
        $summary['next'] = [
            'ship' => $job->getShipKey(),
            'label' => $definition->getLabel(),
            'quantity' => $job->getQuantity(),
            'endsAt' => $job->getEndsAt(),
            'remaining' => max(0, $job->getEndsAt()->getTimestamp() - time()),
        ];

        return $summary;
    }
}
