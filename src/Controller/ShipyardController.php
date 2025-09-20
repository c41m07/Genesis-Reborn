<?php

namespace App\Controller;

use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Shipyard\BuildShips;
use App\Application\UseCase\Shipyard\GetShipyardOverview;
use App\Domain\Entity\ShipDefinition;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeInterface;
use RuntimeException;

class ShipyardController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly GetShipyardOverview $getOverview,
        private readonly BuildShips $buildShips,
        private readonly ProcessShipBuildQueue $shipQueueProcessor,
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

            return $this->render('pages/shipyard/index.php', [
                'title' => 'Chantier spatial',
                'planets' => [],
                'overview' => null,
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'currentUserId' => $userId,
                'activeSection' => 'shipyard',
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

        $this->shipQueueProcessor->process($selectedId);

        $overview = $this->getOverview->execute($selectedId);
        $buildingLevels = $overview['buildingLevels'] ?? [];
        $facilityStatuses = [
            'research_lab' => ($buildingLevels['research_lab'] ?? 0) > 0,
            'shipyard' => ($buildingLevels['shipyard'] ?? 0) > 0,
        ];

        if (!($facilityStatuses['shipyard'] ?? false)) {
            $message = 'Le chantier spatial n’est pas disponible sur cette planète.';
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
            if (!$this->isCsrfTokenValid('shipyard_' . $selectedId, $data['csrf_token'] ?? null)) {
                if ($request->wantsJson()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Session expirée, veuillez réessayer.',
                    ], 400);
                }

                $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
                return $this->redirect($this->baseUrl . '/shipyard?planet=' . $selectedId);
            }

            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;
            $quantity = max(1, $quantity);
            $result = $this->buildShips->execute($selectedId, $userId, $data['ship'] ?? '', $quantity);
            if ($request->wantsJson()) {
                $updated = $this->getOverview->execute($selectedId);
                $planet = $updated['planet'];
                $queue = $this->formatShipQueue($updated['queue'] ?? []);
                $shipKey = (string) ($data['ship'] ?? '');
                $shipEntry = $this->findShipEntry($updated['categories'] ?? [], $shipKey);

                return $this->json([
                    'success' => $result['success'],
                    'message' => $result['message'] ?? ($result['success'] ? 'Production planifiée.' : 'Action impossible.'),
                    'resources' => $this->formatResourceSnapshot($planet),
                    'queue' => $queue,
                    'ship' => $shipEntry ? $this->normalizeShipEntry($shipEntry) : null,
                    'planetId' => $selectedId,
                ], $result['success'] ? 200 : 400);
            }

            if ($result['success']) {
                $this->addFlash('success', $result['message'] ?? 'Production planifiée.');
            } else {
                $this->addFlash('danger', $result['message'] ?? 'Action impossible.');
            }

            return $this->redirect($this->baseUrl . '/shipyard?planet=' . $selectedId);
        }

        $planet = $overview['planet'];
        $activePlanetSummary = [
            'planet' => $planet,
            'resources' => $this->formatResourceSnapshot($planet),
        ];

        return $this->render('pages/shipyard/index.php', [
            'title' => 'Chantier spatial',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'overview' => $overview,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_shipyard' => $this->generateCsrfToken('shipyard_' . $selectedId),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'shipyard',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }

    /**
     * @param array{jobs?: array<int, array<string, mixed>>} $queue
     *
     * Je mets la file de production au propre pour les réponses JSON.
     */
    private function formatShipQueue(array $queue): array
    {
        $jobs = [];

        foreach ($queue['jobs'] ?? [] as $job) {
            $jobs[] = [
                'ship' => (string) ($job['ship'] ?? ''),
                'label' => (string) ($job['label'] ?? ''),
                'quantity' => (int) ($job['quantity'] ?? 0),
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
     *
     * Ce helper me retrouve une entrée de vaisseau dans le catalogue préparé.
     */
    private function findShipEntry(array $categories, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        foreach ($categories as $category) {
            foreach ($category['items'] ?? [] as $item) {
                $definition = $item['definition'] ?? null;
                if ($definition instanceof ShipDefinition && $definition->getKey() === $key) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @param array{definition: ShipDefinition, requirements?: array<string, mixed>, canBuild?: bool} $entry
     *
     * Je renvoie un format simplifié pour le front après une création.
     */
    private function normalizeShipEntry(array $entry): array
    {
        $definition = $entry['definition'];
        if (!$definition instanceof ShipDefinition) {
            throw new RuntimeException('Définition de vaisseau introuvable.');
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
            'canBuild' => (bool) ($entry['canBuild'] ?? false),
            'requirements' => [
                'ok' => (bool) ($requirements['ok'] ?? false),
                'missing' => $missing,
            ],
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }
}
