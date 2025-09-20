<?php

namespace App\Controller;

use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use DateInterval;
use DateTimeImmutable;

class GalaxyController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
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

            return $this->render('galaxy/index.php', [
                'title' => 'Carte galaxie',
                'planets' => [],
                'selectedPlanetId' => null,
                'map' => [],
                'filters' => [
                    'status' => 'all',
                    'query' => '',
                ],
                'summary' => [
                    'totals' => ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0],
                    'activeCount' => 0,
                    'inactiveCount' => 0,
                    'strongCount' => 0,
                ],
                'suggestions' => [],
                'comparisons' => [],
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'activeSection' => 'galaxy',
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
            ]);
        }

        $selectedId = (int) ($request->getQueryParams()['planet'] ?? $planets[0]->getId());
        $selectedPlanet = $this->findPlanet($planets, $selectedId) ?? $planets[0];
        $selectedId = $selectedPlanet->getId();

        $queryParams = $request->getQueryParams();
        $statusFilter = strtolower(trim((string) ($queryParams['status'] ?? 'all')));
        $searchTerm = trim((string) ($queryParams['q'] ?? ''));

        $now = new DateTimeImmutable();
        $mapEntries = [];
        $totals = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0];
        $activeCount = 0;
        $inactiveCount = 0;
        $strongCount = 0;

        foreach ($planets as $planet) {
            $production = [
                'metal' => $planet->getMetalPerHour(),
                'crystal' => $planet->getCrystalPerHour(),
                'hydrogen' => $planet->getHydrogenPerHour(),
                'energy' => $planet->getEnergyPerHour(),
            ];
            $totals['metal'] += $production['metal'];
            $totals['crystal'] += $production['crystal'];
            $totals['hydrogen'] += $production['hydrogen'];
            $totals['energy'] += $production['energy'];

            $activity = $this->classifyActivity($planet->getLastResourceTick(), $now);
            $strength = $this->classifyStrength($production);

            if ($activity['key'] === 'active') {
                ++$activeCount;
            }
            if ($activity['key'] === 'inactive') {
                ++$inactiveCount;
            }
            if ($strength['key'] === 'strong') {
                ++$strongCount;
            }

            $mapEntries[] = [
                'planet' => $planet,
                'coordinates' => $planet->getCoordinates(),
                'coordinateString' => sprintf('%d:%d:%d', $planet->getGalaxy(), $planet->getSystem(), $planet->getPosition()),
                'production' => $production,
                'totalProduction' => $production['metal'] + $production['crystal'] + $production['hydrogen'],
                'activity' => $activity,
                'strength' => $strength,
                'lastActivity' => $planet->getLastResourceTick(),
            ];
        }

        $filteredMap = array_filter($mapEntries, static function (array $entry) use ($statusFilter, $searchTerm): bool {
            if ($statusFilter !== '' && $statusFilter !== 'all') {
                $statusKey = $entry['activity']['key'] ?? '';
                $strengthKey = $entry['strength']['key'] ?? '';
                $statusMatch = $statusFilter === $statusKey
                    || ($statusFilter === 'strong' && $strengthKey === 'strong');
                if (!$statusMatch) {
                    return false;
                }
            }

            if ($searchTerm !== '') {
                $needle = mb_strtolower($searchTerm);
                $nameMatch = str_contains(mb_strtolower($entry['planet']->getName()), $needle);
                $coordMatch = str_contains(mb_strtolower($entry['coordinateString']), $needle);
                if (!$nameMatch && !$coordMatch) {
                    return false;
                }
            }

            return true;
        });

        $suggestions = $this->generateColonizationSuggestions($planets, $now);
        $comparisons = $this->buildAllianceComparisons($mapEntries);

        $levels = $this->buildingStates->getLevels($selectedId);
        $facilityStatuses = [
            'research_lab' => ($levels['research_lab'] ?? 0) > 0,
            'shipyard' => ($levels['shipyard'] ?? 0) > 0,
        ];

        $activePlanetSummary = [
            'planet' => $selectedPlanet,
            'resources' => [
                'metal' => ['value' => $selectedPlanet->getMetal(), 'perHour' => $selectedPlanet->getMetalPerHour()],
                'crystal' => ['value' => $selectedPlanet->getCrystal(), 'perHour' => $selectedPlanet->getCrystalPerHour()],
                'hydrogen' => ['value' => $selectedPlanet->getHydrogen(), 'perHour' => $selectedPlanet->getHydrogenPerHour()],
                'energy' => ['value' => $selectedPlanet->getEnergy(), 'perHour' => $selectedPlanet->getEnergyPerHour()],
            ],
        ];

        return $this->render('galaxy/index.php', [
            'title' => 'Carte galaxie',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'map' => array_values($filteredMap),
            'filters' => [
                'status' => $statusFilter !== '' ? $statusFilter : 'all',
                'query' => $searchTerm,
            ],
            'summary' => [
                'totals' => $totals,
                'activeCount' => $activeCount,
                'inactiveCount' => $inactiveCount,
                'strongCount' => $strongCount,
            ],
            'suggestions' => $suggestions,
            'comparisons' => $comparisons,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'galaxy',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }

    /**
     * @param Planet[] $planets
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
     * @return array{key: string, label: string, tone: string}
     */
    private function classifyActivity(DateTimeImmutable $lastTick, DateTimeImmutable $now): array
    {
        $elapsed = max(0, $now->getTimestamp() - $lastTick->getTimestamp());
        if ($elapsed <= 3600) {
            return ['key' => 'active', 'label' => 'Actif', 'tone' => 'positive'];
        }
        if ($elapsed <= 21600) {
            return ['key' => 'calm', 'label' => 'Calme', 'tone' => 'neutral'];
        }
        if ($elapsed <= 43200) {
            return ['key' => 'idle', 'label' => 'Veille', 'tone' => 'neutral'];
        }

        return ['key' => 'inactive', 'label' => 'Inactif', 'tone' => 'negative'];
    }

    /**
     * @param array{metal: int, crystal: int, hydrogen: int, energy: int} $production
     *
     * @return array{key: string, label: string}
     */
    private function classifyStrength(array $production): array
    {
        $score = $production['metal'] + $production['crystal'] + $production['hydrogen'];
        if ($score >= 45000) {
            return ['key' => 'strong', 'label' => 'Puissante'];
        }
        if ($score >= 18000) {
            return ['key' => 'solid', 'label' => 'Solide'];
        }

        return ['key' => 'developing', 'label' => 'Émergente'];
    }

    /**
     * @param Planet[] $planets
     * @return array<int, array{coordinates: string, distance: int, potential: int}>
     */
    private function generateColonizationSuggestions(array $planets, DateTimeImmutable $now): array
    {
        if ($planets === []) {
            return [];
        }

        $reference = $planets[0];
        $baseSystem = $reference->getSystem();
        $baseGalaxy = $reference->getGalaxy();
        $basePosition = $reference->getPosition();

        $suggestions = [];
        for ($i = 1; $i <= 3; ++$i) {
            $system = max(1, $baseSystem + ($i * 2));
            $position = (($basePosition + ($i * 3) - 1) % 15) + 1;
            $distance = (int) abs($system - $baseSystem) * 12 + abs($position - $basePosition);
            $potential = max(40, 95 - ($i * 8));

            $arrival = $now->add(new DateInterval('PT' . max(1, $distance * 18) . 'M'));

            $suggestions[] = [
                'coordinates' => sprintf('%d:%d:%d', $baseGalaxy, $system, $position),
                'distance' => $distance,
                'potential' => $potential,
                'arrival' => $arrival,
            ];
        }

        return $suggestions;
    }

    /**
     * @param array<int, array<string, mixed>> $mapEntries
     * @return array<int, array{name: string, score: int, trend: int}>
     */
    private function buildAllianceComparisons(array $mapEntries): array
    {
        if ($mapEntries === []) {
            return [];
        }

        $empireScore = 0;
        foreach ($mapEntries as $entry) {
            $empireScore += (int) ($entry['totalProduction'] ?? 0);
        }
        $empireScore = max(1, $empireScore);

        return [
            ['name' => 'Votre empire', 'score' => $empireScore, 'trend' => 0],
            ['name' => 'Coalition Nova', 'score' => (int) round($empireScore * 1.18), 'trend' => 8],
            ['name' => 'Légion Umbra', 'score' => (int) round($empireScore * 0.92), 'trend' => -4],
        ];
    }
}
