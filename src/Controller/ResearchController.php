<?php

namespace App\Controller;

use App\Application\Service\ProcessResearchQueue;
use App\Application\UseCase\Research\GetResearchOverview;
use App\Application\UseCase\Research\StartResearch;
use App\Domain\Entity\ResearchDefinition;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeInterface;
use RuntimeException;

class ResearchController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly GetResearchOverview $getOverview,
        private readonly StartResearch $startResearch,
        private readonly ProcessResearchQueue $researchQueueProcessor,
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
        if (!$planets) {
            $this->addFlash('info', 'Aucune planète disponible.');

            return $this->render('research/index.php', [
                'title' => 'Laboratoire de recherche',
                'planets' => [],
                'overview' => null,
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'currentUserId' => $userId,
                'activeSection' => 'research',
                'selectedPlanetId' => null,
                'activePlanetSummary' => null,
                'csrf_logout' => $this->generateCsrfToken('logout'),
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

        $this->researchQueueProcessor->process($selectedId);

        $overview = $this->getOverview->execute($selectedId);
        $buildingLevels = $overview['buildingLevels'] ?? [];
        $facilityStatuses = [
            'research_lab' => ($buildingLevels['research_lab'] ?? 0) > 0,
            'shipyard' => ($buildingLevels['shipyard'] ?? 0) > 0,
        ];

        if (!($facilityStatuses['research_lab'] ?? false)) {
            $message = 'Le laboratoire de recherche n’est pas disponible sur cette planète.';
            if ($request->wantsJson()) {
                return $this->json([
                    'success' => false,
                    'message' => $message,
                    'planetId' => $selectedId,
                ], 403);
            }

            $this->addFlash('warning', $message);

            return $this->redirect($this->baseUrl . '/colony?planet=' . $selectedId);
        }

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            if (!$this->isCsrfTokenValid('start_research_' . $selectedId, $data['csrf_token'] ?? null)) {
                if ($request->wantsJson()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Session expirée, veuillez réessayer.',
                    ], 400);
                }

                $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
                return $this->redirect($this->baseUrl . '/research?planet=' . $selectedId);
            }

            $result = $this->startResearch->execute($selectedId, $userId, $data['research'] ?? '');
            if ($request->wantsJson()) {
                $updated = $this->getOverview->execute($selectedId);
                $planet = $updated['planet'];
                $queue = $this->formatResearchQueue($updated['queue'] ?? []);
                $researchKey = (string) ($data['research'] ?? '');
                $researchEntry = $this->findResearchEntry($updated['categories'] ?? [], $researchKey);

                return $this->json([
                    'success' => $result['success'],
                    'message' => $result['message'] ?? ($result['success'] ? 'Recherche planifiée.' : 'Action impossible.'),
                    'resources' => $this->formatResourceSnapshot($planet),
                    'queue' => $queue,
                    'research' => $researchEntry ? $this->normalizeResearchEntry($researchEntry) : null,
                    'planetId' => $selectedId,
                ], $result['success'] ? 200 : 400);
            }

            if ($result['success']) {
                $this->addFlash('success', $result['message'] ?? 'Recherche planifiée.');
            } else {
                $this->addFlash('danger', $result['message'] ?? 'Action impossible.');
            }

            return $this->redirect($this->baseUrl . '/research?planet=' . $selectedId);
        }

        $planet = $overview['planet'];
        $activePlanetSummary = [
            'planet' => $planet,
            'resources' => $this->formatResourceSnapshot($planet),
        ];

        return $this->render('research/index.php', [
            'title' => 'Laboratoire de recherche',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'overview' => $overview,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_start' => $this->generateCsrfToken('start_research_' . $selectedId),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'research',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
}

    /**
     * @param array{jobs?: array<int, array<string, mixed>>} $queue
     */
    private function formatResearchQueue(array $queue): array
    {
        $jobs = [];

        foreach ($queue['jobs'] ?? [] as $job) {
            $jobs[] = [
                'research' => (string) ($job['research'] ?? ''),
                'label' => (string) ($job['label'] ?? ''),
                'targetLevel' => (int) ($job['targetLevel'] ?? 0),
                'remaining' => (int) ($job['remaining'] ?? 0),
                'endsAt' => $this->formatDateTime($job['endsAt'] ?? null),
            ];
        }

        return [
            'count' => count($jobs),
            'jobs' => $jobs,
        ];
    }

    /**
     * @param array<int, array{items?: array<int, array<string, mixed>>}> $categories
     */
    private function findResearchEntry(array $categories, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        foreach ($categories as $category) {
            foreach ($category['items'] ?? [] as $item) {
                $definition = $item['definition'] ?? null;
                if ($definition instanceof ResearchDefinition && $definition->getKey() === $key) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @param array{definition: ResearchDefinition, level?: int, maxLevel?: int, progress?: float, nextCost?: array<string, int>, nextTime?: int, requirements?: array<string, mixed>, canResearch?: bool} $entry
     */
    private function normalizeResearchEntry(array $entry): array
    {
        $definition = $entry['definition'];
        if (!$definition instanceof ResearchDefinition) {
            throw new RuntimeException('Définition de recherche introuvable.');
        }

        $requirements = $entry['requirements'] ?? ['ok' => true, 'missing' => []];
        $missing = [];
        foreach ($requirements['missing'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $missing[] = [
                'type' => (string) ($item['type'] ?? ''),
                'key' => (string) ($item['key'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'level' => (int) ($item['level'] ?? 0),
                'current' => (int) ($item['current'] ?? 0),
            ];
        }

        return [
            'key' => $definition->getKey(),
            'label' => $definition->getLabel(),
            'level' => (int) ($entry['level'] ?? 0),
            'maxLevel' => (int) ($entry['maxLevel'] ?? 0),
            'progress' => (float) ($entry['progress'] ?? 0.0),
            'nextCost' => array_map(static fn ($value) => (int) $value, $entry['nextCost'] ?? []),
            'nextTime' => (int) ($entry['nextTime'] ?? 0),
            'requirements' => [
                'ok' => (bool) ($requirements['ok'] ?? false),
                'missing' => $missing,
            ],
            'canResearch' => (bool) ($entry['canResearch'] ?? false),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }
}
