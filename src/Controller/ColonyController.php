<?php

namespace App\Controller;

use App\Application\Service\ProcessBuildQueue;
use App\Application\UseCase\Building\GetBuildingsOverview;
use App\Application\UseCase\Building\UpgradeBuilding;
use App\Domain\Entity\BuildingDefinition;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeInterface;
use RuntimeException;

class ColonyController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly GetBuildingsOverview $getOverview,
        private readonly UpgradeBuilding $upgradeBuilding,
        private readonly ProcessBuildQueue $buildQueueProcessor,
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

            return $this->render('pages/colony/index.php', [
                'title' => 'Bâtiments',
                'planets' => [],
                'overview' => null,
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'currentUserId' => $userId,
                'activeSection' => 'colony',
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

        $this->buildQueueProcessor->process($selectedId);

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            if (!$this->isCsrfTokenValid('upgrade_building_' . $selectedId, $data['csrf_token'] ?? null)) {
                if ($request->wantsJson()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Session expirée, veuillez réessayer.',
                    ], 400);
                }

                $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
                return $this->redirect($this->baseUrl . '/colony?planet=' . $selectedId);
            }

            $result = $this->upgradeBuilding->execute($selectedId, $userId, $data['building'] ?? '');
            if ($request->wantsJson()) {
                $overview = $this->getOverview->execute($selectedId);
                $planet = $overview['planet'];
                $queue = $this->formatBuildQueue($overview['queue'] ?? []);
                $buildingKey = (string) ($data['building'] ?? '');
                $buildingEntry = $this->findBuildingEntry($overview['buildings'] ?? [], $buildingKey);

                return $this->json([
                    'success' => $result['success'],
                    'message' => $result['message'] ?? ($result['success'] ? 'Construction planifiée.' : 'Action impossible.'),
                    'resources' => $this->formatResourceSnapshot($planet),
                    'queue' => $queue,
                    'building' => $buildingEntry ? $this->normalizeBuildingEntry($buildingEntry) : null,
                    'planetId' => $selectedId,
                ], $result['success'] ? 200 : 400);
            }

            if ($result['success']) {
                $this->addFlash('success', $result['message'] ?? 'Construction planifiée.');
            } else {
                $this->addFlash('danger', $result['message'] ?? 'Action impossible.');
            }

            return $this->redirect($this->baseUrl . '/colony?planet=' . $selectedId);
        }

        $overview = $this->getOverview->execute($selectedId);
        $planet = $overview['planet'];
        $levels = $overview['levels'] ?? [];
        $facilityStatuses = [
            'research_lab' => ($levels['research_lab'] ?? 0) > 0,
            'shipyard' => ($levels['shipyard'] ?? 0) > 0,
        ];
        $activePlanetSummary = [
            'planet' => $planet,
            'resources' => $this->formatResourceSnapshot($planet),
        ];

        return $this->render('pages/colony/index.php', [
            'title' => 'Bâtiments',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'overview' => $overview,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_upgrade' => $this->generateCsrfToken('upgrade_building_' . $selectedId),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'colony',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }

    /**
     * @param array{jobs?: array<int, array<string, mixed>>} $queue
     *
     * Je transforme ici la file brute pour que la vue soit facile à lire.
     */
    private function formatBuildQueue(array $queue): array
    {
        $jobs = [];

        foreach ($queue['jobs'] ?? [] as $job) {
            $jobs[] = [
                'building' => (string) ($job['building'] ?? ''),
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
     * @param array<int, array{definition?: mixed}> $buildings
     *
     * Ce petit helper me permet de retrouver le bâtiment ciblé par sa clé.
     */
    private function findBuildingEntry(array $buildings, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        foreach ($buildings as $entry) {
            $definition = $entry['definition'] ?? null;
            if ($definition instanceof BuildingDefinition && $definition->getKey() === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array{definition: BuildingDefinition, level?: int, canUpgrade?: bool, cost?: array<string, int>, time?: int, production?: array<string, mixed>, consumption?: array<string, array<string, int>>, storage?: array<string, array<string, int>>, requirements?: array<string, mixed>} $entry
     *
     * Je normalise les infos du bâtiment pour que le front puisse les exploiter tranquille.
     */
    private function normalizeBuildingEntry(array $entry): array
    {
        $definition = $entry['definition'];
        if (!$definition instanceof BuildingDefinition) {
            throw new RuntimeException('Définition de bâtiment introuvable.');
        }

        $consumption = [];
        foreach ($entry['consumption'] ?? [] as $resource => $values) {
            if (!is_array($values)) {
                continue;
            }

            $consumption[$resource] = [
                'current' => (int) ($values['current'] ?? 0),
                'next' => (int) ($values['next'] ?? 0),
                'delta' => (int) ($values['delta'] ?? 0),
            ];
        }

        $storage = $entry['storage'] ?? [];
        $normalizeStorage = static function (array $values): array {
            $normalized = [];
            foreach ($values as $resource => $value) {
                $normalized[$resource] = (int) $value;
            }

            return $normalized;
        };

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

        $production = $entry['production'] ?? [];

        return [
            'key' => $definition->getKey(),
            'label' => $definition->getLabel(),
            'image' => $definition->getImage(),
            'level' => (int) ($entry['level'] ?? 0),
            'canUpgrade' => (bool) ($entry['canUpgrade'] ?? false),
            'affordable' => (bool) ($entry['affordable'] ?? false),
            'missingResources' => array_map(static fn ($value) => (int) $value, $entry['missingResources'] ?? []),
            'cost' => array_map(static fn ($value) => (int) $value, $entry['cost'] ?? []),
            'time' => (int) ($entry['time'] ?? 0),
            'baseTime' => (int) ($entry['baseTime'] ?? 0),
            'production' => [
                'resource' => (string) ($production['resource'] ?? ''),
                'current' => (int) ($production['current'] ?? 0),
                'next' => (int) ($production['next'] ?? 0),
                'delta' => (int) ($production['delta'] ?? 0),
            ],
            'consumption' => $consumption,
            'storage' => [
                'current' => $normalizeStorage($storage['current'] ?? []),
                'next' => $normalizeStorage($storage['next'] ?? []),
                'delta' => $normalizeStorage($storage['delta'] ?? []),
            ],
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
