<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeImmutable;

class GalaxyController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface        $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly UserRepositoryInterface          $users,
        ViewRenderer                                      $renderer,
        SessionInterface                                  $session,
        FlashBag                                          $flashBag,
        CsrfTokenManager                                  $csrfTokenManager,
        string                                            $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function index(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->redirect($this->baseUrl . '/login');
        }

        $viewOptions = [
            'all' => 'Toutes les positions',
            'colonizable' => 'Colonisables',
            'inactive' => 'Planètes inactives',
        ];

        $ownedPlanets = $this->planets->findByUser($userId);
        $query = $request->getQueryParams();

        if ($ownedPlanets === []) {
            $galaxy = max(1, (int)($query['galaxy'] ?? 1));
            $system = max(1, (int)($query['system'] ?? 1));
            $viewMode = $this->sanitizeViewMode($query['view'] ?? 'all', $viewOptions);
            $searchTerm = trim((string)($query['q'] ?? ''));

            $systemPlanets = $this->planets->findByCoordinates($galaxy, $system);
            $owners = $this->hydrateOwners($systemPlanets);
            $slots = $this->buildSlots($systemPlanets, $owners, $galaxy, $system, $userId, $viewMode, $searchTerm);
            $summary = $this->buildSummary($slots, $galaxy, $system);
            $players = $this->buildPlayerSummaries($slots);

            $this->addFlash('info', 'Aucune planète disponible.');

            return $this->render('pages/galaxy/index.php', [
                'title' => 'Carte galaxie',
                'planets' => [],
                'selectedPlanetId' => null,
                'slots' => $slots,
                'summary' => $summary,
                'players' => $players,
                'filters' => [
                    'galaxy' => $galaxy,
                    'system' => $system,
                    'view' => $viewMode,
                    'query' => $searchTerm,
                    'options' => $viewOptions,
                ],
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'activeSection' => 'galaxy',
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
            ]);
        }

        $selectedId = (int)($query['planet'] ?? $ownedPlanets[0]->getId());
        $selectedPlanet = $this->findPlanet($ownedPlanets, $selectedId) ?? $ownedPlanets[0];
        $selectedId = $selectedPlanet->getId();

        $galaxy = max(1, (int)($query['galaxy'] ?? $selectedPlanet->getGalaxy()));
        $system = max(1, (int)($query['system'] ?? $selectedPlanet->getSystem()));
        $viewMode = $this->sanitizeViewMode($query['view'] ?? 'all', $viewOptions);
        $searchTerm = trim((string)($query['q'] ?? ''));

        $systemPlanets = $this->planets->findByCoordinates($galaxy, $system);
        $owners = $this->hydrateOwners($systemPlanets);
        $slots = $this->buildSlots($systemPlanets, $owners, $galaxy, $system, $userId, $viewMode, $searchTerm);
        $summary = $this->buildSummary($slots, $galaxy, $system);
        $players = $this->buildPlayerSummaries($slots);

        $levels = $this->buildingStates->getLevels($selectedId);
        $facilityStatuses = [
            'research_lab' => ($levels['research_lab'] ?? 0) > 0,
            'shipyard' => ($levels['shipyard'] ?? 0) > 0,
        ];

        $planet = $selectedPlanet;
        $activePlanetSummary = [
            'planet' => $planet,
            'resources' => [
                'metal' => ['value' => $planet->getMetal(), 'perHour' => $planet->getMetalPerHour()],
                'crystal' => ['value' => $planet->getCrystal(), 'perHour' => $planet->getCrystalPerHour()],
                'hydrogen' => ['value' => $planet->getHydrogen(), 'perHour' => $planet->getHydrogenPerHour()],
                'energy' => ['value' => $planet->getEnergy(), 'perHour' => $planet->getEnergyPerHour()],
            ],
        ];

        return $this->render('pages/galaxy/index.php', [
            'title' => 'Carte galaxie',
            'planets' => $ownedPlanets,
            'selectedPlanetId' => $selectedId,
            'slots' => $slots,
            'summary' => $summary,
            'players' => $players,
            'filters' => [
                'galaxy' => $galaxy,
                'system' => $system,
                'view' => $viewMode,
                'query' => $searchTerm,
                'options' => $viewOptions,
            ],
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
     * @param array<string, string> $options
     */
    private function sanitizeViewMode(string $value, array $options): string
    {
        $value = strtolower(trim($value));

        return array_key_exists($value, $options) ? $value : 'all';
    }

    /**
     * @param Planet[] $planets
     * @return array<int, array{id: int, name: string}>
     *
     * Ici je prépare un petit index des propriétaires pour l’affichage.
     */
    private function hydrateOwners(array $planets): array
    {
        $ownerIds = [];
        foreach ($planets as $planet) {
            $ownerIds[$planet->getUserId()] = true;
        }

        $owners = [];
        foreach (array_keys($ownerIds) as $ownerId) {
            $user = $this->users->find($ownerId);
            $owners[$ownerId] = [
                'id' => $ownerId,
                'name' => $user ? $user->getUsername() : 'Commandant #' . $ownerId,
            ];
        }

        return $owners;
    }

    /**
     * @param Planet[] $systemPlanets
     * @param array<int, array{id: int, name: string}> $owners
     * @return array<int, array<string, mixed>>
     *
     * Je construis les 16 cases du système avec toutes les infos utiles.
     */
    private function buildSlots(
        array  $systemPlanets,
        array  $owners,
        int    $galaxy,
        int    $system,
        int    $userId,
        string $viewMode,
        string $searchTerm
    ): array {
        $now = new DateTimeImmutable();
        $positionMap = [];
        foreach ($systemPlanets as $planet) {
            $positionMap[$planet->getPosition()] = $planet;
        }

        $needle = mb_strtolower($searchTerm);
        $slots = [];
        for ($position = 1; $position <= 16; ++$position) {
            $planet = $positionMap[$position] ?? null;
            $coordinates = sprintf('%d:%d:%d', $galaxy, $system, $position);

            $slot = [
                'position' => $position,
                'coordinates' => $coordinates,
                'planet' => $planet,
                'owner' => null,
                'activity' => null,
                'strength' => null,
                'production' => ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 0],
                'lastActivity' => null,
                'isPlayer' => false,
                'isEmpty' => $planet === null,
            ];

            if ($planet) {
                $production = [
                    'metal' => $planet->getMetalPerHour(),
                    'crystal' => $planet->getCrystalPerHour(),
                    'hydrogen' => $planet->getHydrogenPerHour(),
                    'energy' => $planet->getEnergyPerHour(),
                ];
                $activity = $this->classifyActivity($planet->getLastResourceTick(), $now);
                $strength = $this->classifyStrength($production);
                $owner = $owners[$planet->getUserId()] ?? [
                    'id' => $planet->getUserId(),
                    'name' => 'Commandant #' . $planet->getUserId(),
                ];

                $slot['owner'] = $owner;
                $slot['activity'] = $activity;
                $slot['strength'] = $strength;
                $slot['production'] = $production;
                $slot['lastActivity'] = $planet->getLastResourceTick();
                $slot['isPlayer'] = $planet->getUserId() === $userId;
            }

            $matchesFilter = match ($viewMode) {
                'colonizable' => $slot['isEmpty'],
                'inactive' => !$slot['isEmpty'] && ($slot['activity']['key'] ?? '') === 'inactive',
                default => true,
            };

            $matchesSearch = true;
            if ($searchTerm !== '') {
                $matchesSearch = false;
                $haystacks = [$coordinates];
                if ($planet) {
                    $haystacks[] = $planet->getName();
                }
                if (!empty($slot['owner']['name'])) {
                    $haystacks[] = $slot['owner']['name'];
                }

                foreach ($haystacks as $value) {
                    if (str_contains(mb_strtolower((string)$value), $needle)) {
                        $matchesSearch = true;
                        break;
                    }
                }
            }

            $slot['visible'] = $matchesFilter && $matchesSearch;
            $slot['highlight'] = $matchesSearch && $searchTerm !== '' && $slot['visible'];
            $slot['statusKey'] = $slot['isEmpty'] ? 'empty' : ($slot['activity']['key'] ?? 'unknown');

            $slots[] = $slot;
        }

        return $slots;
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
        $score = max(0, $production['metal']) + max(0, $production['crystal']) + max(0, $production['hydrogen']);
        if ($score >= 45000) {
            return ['key' => 'strong', 'label' => 'Puissante'];
        }
        if ($score >= 18000) {
            return ['key' => 'solid', 'label' => 'Solide'];
        }

        return ['key' => 'developing', 'label' => 'Émergente'];
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     * @return array<string, mixed>
     */
    private function buildSummary(array $slots, int $galaxy, int $system): array
    {
        $summary = [
            'galaxy' => $galaxy,
            'system' => $system,
            'occupied' => 0,
            'empty' => 0,
            'activity' => [
                'active' => 0,
                'calm' => 0,
                'idle' => 0,
                'inactive' => 0,
            ],
            'strong' => 0,
            'visibleCount' => 0,
        ];

        foreach ($slots as $slot) {
            if (!empty($slot['visible'])) {
                ++$summary['visibleCount'];
            }

            if (!empty($slot['isEmpty'])) {
                ++$summary['empty'];
                continue;
            }

            ++$summary['occupied'];

            $activityKey = $slot['activity']['key'] ?? null;
            if ($activityKey !== null && isset($summary['activity'][$activityKey])) {
                ++$summary['activity'][$activityKey];
            }

            if (($slot['strength']['key'] ?? '') === 'strong') {
                ++$summary['strong'];
            }
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     * @return array<int, array<string, mixed>>
     */
    private function buildPlayerSummaries(array $slots): array
    {
        $players = [];

        foreach ($slots as $slot) {
            if (!empty($slot['isEmpty']) || empty($slot['owner'])) {
                continue;
            }

            $ownerId = (int)($slot['owner']['id'] ?? 0);
            if (!isset($players[$ownerId])) {
                $players[$ownerId] = [
                    'id' => $ownerId,
                    'name' => (string)($slot['owner']['name'] ?? ''),
                    'planets' => 0,
                    'inactive' => 0,
                    'strong' => 0,
                    'production' => 0,
                    'lastActivity' => null,
                ];
            }

            ++$players[$ownerId]['planets'];
            if (($slot['activity']['key'] ?? '') === 'inactive') {
                ++$players[$ownerId]['inactive'];
            }
            if (($slot['strength']['key'] ?? '') === 'strong') {
                ++$players[$ownerId]['strong'];
            }

            $players[$ownerId]['production'] += max(0, (int)($slot['production']['metal'] ?? 0))
                + max(0, (int)($slot['production']['crystal'] ?? 0))
                + max(0, (int)($slot['production']['hydrogen'] ?? 0));

            $lastActivity = $slot['lastActivity'] ?? null;
            if ($lastActivity instanceof DateTimeImmutable) {
                $stored = $players[$ownerId]['lastActivity'];
                if (!$stored instanceof DateTimeImmutable || $lastActivity > $stored) {
                    $players[$ownerId]['lastActivity'] = $lastActivity;
                }
            }
        }

        foreach ($players as &$player) {
            $player['status'] = $this->resolvePlayerStatus(
                $player['planets'],
                $player['inactive'],
                $player['strong']
            );
        }
        unset($player);

        usort($players, static fn (array $a, array $b): int => $b['production'] <=> $a['production']);

        return $players;
    }

    /**
     * @return array{key: string, label: string, tone: string}
     */
    private function resolvePlayerStatus(int $planets, int $inactive, int $strong): array
    {
        if ($planets > 0 && $inactive >= $planets) {
            return ['key' => 'inactive', 'label' => 'Inactif', 'tone' => 'negative'];
        }

        if ($strong > 0) {
            return ['key' => 'strong', 'label' => 'Puissant', 'tone' => 'positive'];
        }

        return ['key' => 'active', 'label' => 'Actif', 'tone' => 'neutral'];
    }

    /**
     * @param Planet[] $planets
     *
     * Je balaie la liste pour retrouver la planète demandée.
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
}
