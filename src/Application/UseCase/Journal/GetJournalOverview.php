<?php

declare(strict_types=1);

namespace App\Application\UseCase\Journal;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ShipCatalog;
use DateTimeImmutable;
use InvalidArgumentException;

final class GetJournalOverview
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly BuildQueueRepositoryInterface $buildQueue,
        private readonly ResearchQueueRepositoryInterface $researchQueue,
        private readonly ShipBuildQueueRepositoryInterface $shipQueue,
        private readonly ProcessBuildQueue $processBuildQueue,
        private readonly ProcessResearchQueue $processResearchQueue,
        private readonly ProcessShipBuildQueue $processShipBuildQueue,
        private readonly BuildingCatalog $buildingCatalog,
        private readonly ResearchCatalog $researchCatalog,
        private readonly ShipCatalog $shipCatalog
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{
     *     planets: array<int, Planet>,
     *     selectedPlanetId: ?int,
     *     events: array<int, array<string, mixed>>,
     *     insights: array<string, mixed>,
     *     activePlanetSummary: array<string, mixed>|null,
     *     facilityStatuses: array<string, bool>,
     *     messages: list<array{type: string, message: string}>
     * }
     */
    public function execute(int $userId, array $query): array
    {
        $planets = $this->planets->findByUser($userId);
        if ($planets === []) {
            return [
                'planets' => [],
                'selectedPlanetId' => null,
                'events' => [],
                'insights' => [
                    'buildQueue' => 0,
                    'researchQueue' => 0,
                    'shipQueue' => 0,
                    'nextEvent' => null,
                ],
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
                'messages' => [
                    ['type' => 'info', 'message' => 'Aucune planète disponible.'],
                ],
            ];
        }

        $selectedId = (int)($query['planet'] ?? $planets[0]->getId());
        $selectedPlanet = $this->findPlanet($planets, $selectedId) ?? $planets[0];
        $selectedId = $selectedPlanet->getId();

        $this->processBuildQueue->process($selectedId);
        $this->processResearchQueue->process($selectedId);
        $this->processShipBuildQueue->process($selectedId);

        $buildJobs = $this->buildQueue->getActiveQueue($selectedId);
        $researchJobs = $this->researchQueue->getActiveQueue($selectedId);
        $shipJobs = $this->shipQueue->getActiveQueue($selectedId);

        $buildingLevels = $this->buildingStates->getLevels($selectedId);
        $facilityStatuses = [
            'research_lab' => ($buildingLevels['research_lab'] ?? 0) > 0,
            'shipyard' => ($buildingLevels['shipyard'] ?? 0) > 0,
        ];

        $events = array_merge(
            $this->mapBuildingEvents($buildJobs),
            $this->mapResearchEvents($researchJobs),
            $this->mapShipEvents($shipJobs)
        );

        usort($events, static fn (array $a, array $b): int => $a['endsAt'] <=> $b['endsAt']);

        $nextEvent = null;
        $now = time();
        foreach ($events as $event) {
            if ($event['endsAt'] instanceof DateTimeImmutable && $event['endsAt']->getTimestamp() >= $now) {
                $nextEvent = $event;
                break;
            }
        }

        $activePlanetSummary = [
            'planet' => $selectedPlanet,
            'resources' => [
                'metal' => ['value' => $selectedPlanet->getMetal(), 'perHour' => $selectedPlanet->getMetalPerHour()],
                'crystal' => ['value' => $selectedPlanet->getCrystal(), 'perHour' => $selectedPlanet->getCrystalPerHour()],
                'hydrogen' => ['value' => $selectedPlanet->getHydrogen(), 'perHour' => $selectedPlanet->getHydrogenPerHour()],
                'energy' => ['value' => $selectedPlanet->getEnergy(), 'perHour' => $selectedPlanet->getEnergyPerHour()],
            ],
        ];

        return [
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'events' => $events,
            'insights' => [
                'buildQueue' => count($buildJobs),
                'researchQueue' => count($researchJobs),
                'shipQueue' => count($shipJobs),
                'nextEvent' => $nextEvent,
            ],
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
            'messages' => [],
        ];
    }

    /**
     * @param array<int, Planet> $planets
     */
    private function findPlanet(array $planets, int $planetId): ?Planet
    {
        foreach ($planets as $planet) {
            if ($planet->getId() === $planetId) {
                return $planet;
            }
        }

        return null;
    }

    /**
     * @param array<int, \App\Domain\Entity\BuildJob> $jobs
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapBuildingEvents(array $jobs): array
    {
        $events = [];
        foreach ($jobs as $job) {
            $label = $job->getBuildingKey();
            try {
                $definition = $this->buildingCatalog->get($job->getBuildingKey());
                $label = $definition->getLabel();
            } catch (InvalidArgumentException $exception) {
                // definition missing, keep technical key
            }

            $events[] = $this->createEvent(
                'buildings',
                $label,
                'Amélioration vers le niveau ' . $job->getTargetLevel(),
                $job->getEndsAt()
            );
        }

        return $events;
    }

    /**
     * @param array<int, \App\Domain\Entity\ResearchJob> $jobs
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapResearchEvents(array $jobs): array
    {
        $events = [];
        foreach ($jobs as $job) {
            $label = $job->getResearchKey();
            try {
                $definition = $this->researchCatalog->get($job->getResearchKey());
                $label = $definition->getLabel();
            } catch (InvalidArgumentException $exception) {
                // keep technical key when catalog entry missing
            }

            $events[] = $this->createEvent(
                'research',
                $label,
                'Niveau ' . $job->getTargetLevel() . ' en cours',
                $job->getEndsAt()
            );
        }

        return $events;
    }

    /**
     * @param array<int, \App\Domain\Entity\ShipBuildJob> $jobs
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapShipEvents(array $jobs): array
    {
        $events = [];
        foreach ($jobs as $job) {
            $label = $job->getShipKey();
            try {
                $definition = $this->shipCatalog->get($job->getShipKey());
                $label = $definition->getLabel();
            } catch (InvalidArgumentException $exception) {
                // keep technical key when catalog entry missing
            }

            $events[] = $this->createEvent(
                'shipyard',
                $label,
                $job->getQuantity() . ' unité(s) en construction',
                $job->getEndsAt()
            );
        }

        return $events;
    }

    /**
     * @return array{type: string, icon: string, title: string, description: string, endsAt: DateTimeImmutable, remaining: int}
     */
    private function createEvent(string $type, string $title, string $description, DateTimeImmutable $endsAt): array
    {
        $icons = [
            'buildings' => 'buildings',
            'research' => 'research',
            'shipyard' => 'shipyard',
        ];
        $icon = $icons[$type] ?? 'overview';
        $remaining = max(0, $endsAt->getTimestamp() - time());

        return [
            'type' => $type,
            'icon' => $icon,
            'title' => $title,
            'description' => $description,
            'endsAt' => $endsAt,
            'remaining' => $remaining,
        ];
    }
}
