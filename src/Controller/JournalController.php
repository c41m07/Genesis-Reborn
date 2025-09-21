<?php

namespace App\Controller;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeImmutable;
use InvalidArgumentException;

class JournalController extends AbstractController
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
        private readonly ShipCatalog $shipCatalog,
        ViewRenderer $renderer,
        SessionInterface $session,
        FlashBag $flashBag,
        CsrfTokenManager $csrfTokenManager,
        string $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function index(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->redirect($this->baseUrl . '/login');
        }

        $planets = $this->planets->findByUser($userId);
        if ($planets === []) {
            $this->addFlash('info', 'Aucune planète disponible.');

            return $this->render('pages/journal/index.php', [
                'title' => 'Journal de bord',
                'planets' => [],
                'selectedPlanetId' => null,
                'events' => [],
                'insights' => [
                    'buildQueue' => 0,
                    'researchQueue' => 0,
                    'shipQueue' => 0,
                    'nextEvent' => null,
                ],
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'activeSection' => 'journal',
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
            ]);
        }

        $selectedId = (int) ($request->getQueryParams()['planet'] ?? $planets[0]->getId());
        $selectedPlanet = null;
        foreach ($planets as $planet) {
            if ($planet->getId() === $selectedId) {
                $selectedPlanet = $planet;
                break;
            }
        }

        if (!$selectedPlanet) {
            $selectedPlanet = $planets[0];
            $selectedId = $selectedPlanet->getId();
        }

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

        return $this->render('pages/journal/index.php', [
            'title' => 'Journal de bord',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'events' => $events,
            'insights' => [
                'buildQueue' => count($buildJobs),
                'researchQueue' => count($researchJobs),
                'shipQueue' => count($shipJobs),
                'nextEvent' => $nextEvent,
            ],
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'journal',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }

    /**
     * @param array<int, \App\Domain\Entity\BuildJob> $jobs
     * @return array<int, array<string, mixed>>
     *
     * Je transforme la file des constructions pour alimenter le journal.
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
                // Ici je garde la clé brute si jamais la définition a disparu.
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
     * @return array<int, array<string, mixed>>
     *
     * Pareil ici, mais pour les recherches en cours.
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
                // Ici aussi je garde la clé si le catalogue n’a pas la fiche.
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
     * @return array<int, array<string, mixed>>
     *
     * Même logique pour les chantiers navals afin d’avoir un suivi complet.
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
                // Si je n’ai pas la définition, je conserve le nom technique pour debug.
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
     *
     * Je centralise ici le formatage d’un événement affiché dans l’UI.
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
