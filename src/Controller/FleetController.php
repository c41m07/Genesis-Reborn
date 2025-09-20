<?php

namespace App\Controller;

use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\FleetNavigationService;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeImmutable;
use InvalidArgumentException;

class FleetController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly FleetRepositoryInterface $fleets,
        private readonly ShipCatalog $shipCatalog,
        private readonly ProcessShipBuildQueue $shipQueueProcessor,
        private readonly FleetNavigationService $navigationService,
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

            return $this->render('fleet/index.php', [
                'title' => 'Flotte',
                'planets' => [],
                'selectedPlanetId' => null,
                'fleetOverview' => [
                    'ships' => [],
                    'totalShips' => 0,
                    'power' => 0,
                ],
                'availableShips' => [],
                'catalogCategories' => $this->shipCatalog->groupedByCategory(),
                'submittedComposition' => [],
                'submittedDestination' => ['galaxy' => 1, 'system' => 1, 'position' => 1],
                'planResult' => null,
                'planErrors' => [],
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'csrf_plan' => $this->generateCsrfToken('fleet_plan_0'),
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'activeSection' => 'fleet',
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

        $this->shipQueueProcessor->process($selectedId);

        $buildingLevels = $this->buildingStates->getLevels($selectedId);
        $facilityStatuses = [
            'research_lab' => ($buildingLevels['research_lab'] ?? 0) > 0,
            'shipyard' => ($buildingLevels['shipyard'] ?? 0) > 0,
        ];

        $fleet = $this->fleets->getFleet($selectedId);
        $fleetShips = [];
        $availableShips = [];
        $totalShips = 0;
        $totalPower = 0;
        $shipStats = [];

        foreach ($fleet as $shipKey => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                continue;
            }

            $definition = null;
            try {
                $definition = $this->shipCatalog->get($shipKey);
            } catch (InvalidArgumentException $exception) {
                // Unknown ship in the catalogue – skip fancy data but keep counts.
            }

            $label = $definition ? $definition->getLabel() : $shipKey;
            $stats = $definition ? $definition->getStats() : [];
            $attack = (int) ($stats['attaque'] ?? 0);
            $defense = (int) ($stats['défense'] ?? 0);
            $speed = (int) ($stats['vitesse'] ?? 0);
            $category = $definition ? $definition->getCategory() : 'Divers';
            $role = $definition ? $definition->getRole() : '';
            $image = $definition ? $definition->getImage() : null;
            $fuelRate = 0;
            if ($definition) {
                $baseCost = $definition->getBaseCost();
                $fuelRate = (int) max(1, ceil(($baseCost['hydrogen'] ?? 0) / 25));
            }

            $power = max(0, ($attack + $defense) * $quantity);
            $totalPower += $power;
            $totalShips += $quantity;

            $entry = [
                'key' => $shipKey,
                'label' => $label,
                'quantity' => $quantity,
                'attack' => $attack,
                'defense' => $defense,
                'speed' => $speed,
                'category' => $category,
                'role' => $role,
                'image' => $image,
                'fuelRate' => $fuelRate,
            ];

            $fleetShips[] = $entry;
            $availableShips[] = $entry;

            $shipStats[$shipKey] = [
                'speed' => $speed > 0 ? $speed : max(1, $fuelRate * 2),
                'fuel_per_distance' => max(0.1, $fuelRate ?: 1),
            ];
        }

        usort($fleetShips, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);
        usort($availableShips, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $origin = $selectedPlanet->getCoordinates();
        $submittedDestination = [
            'galaxy' => $origin['galaxy'],
            'system' => $origin['system'],
            'position' => $origin['position'],
        ];
        $submittedComposition = [];
        $planErrors = [];
        $planResult = null;

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            if (!$this->isCsrfTokenValid('fleet_plan_' . $selectedId, $data['csrf_token'] ?? null)) {
                $planErrors[] = 'Session expirée, veuillez recharger la page.';
            } else {
                $destination = [
                    'galaxy' => max(1, (int) ($data['destination_galaxy'] ?? $origin['galaxy'])),
                    'system' => max(1, (int) ($data['destination_system'] ?? $origin['system'])),
                    'position' => max(1, (int) ($data['destination_position'] ?? $origin['position'])),
                ];
                $submittedDestination = $destination;

                $composition = [];
                foreach ($availableShips as $ship) {
                    $key = $ship['key'];
                    $requested = (int) ($data['composition'][$key] ?? 0);
                    if ($requested < 0) {
                        $requested = 0;
                    }
                    $assigned = min($requested, $ship['quantity']);
                    $submittedComposition[$key] = $assigned;
                    if ($assigned > 0) {
                        $composition[$key] = $assigned;
                    }
                }

                if ($composition === []) {
                    $planErrors[] = 'Sélectionnez au moins un vaisseau pour planifier un trajet.';
                }

                $speedFactor = 1.0;
                if (isset($data['speed_factor'])) {
                    $speedFactorInput = (float) $data['speed_factor'];
                    if ($speedFactorInput > 1) {
                        $speedFactorInput /= 100;
                    }
                    $speedFactor = max(0.1, min(1.0, $speedFactorInput));
                }

                if ($planErrors === []) {
                    try {
                        $plan = $this->navigationService->plan(
                            $origin,
                            $destination,
                            $composition,
                            $shipStats,
                            new DateTimeImmutable(),
                            [],
                            $speedFactor
                        );
                        $planResult = [
                            'plan' => $plan,
                            'composition' => $composition,
                            'destination' => $destination,
                            'speedFactor' => $speedFactor,
                        ];
                    } catch (InvalidArgumentException $exception) {
                        $planErrors[] = $exception->getMessage();
                    }
                }
            }
        }

        foreach ($availableShips as $ship) {
            $submittedComposition[$ship['key']] = $submittedComposition[$ship['key']] ?? 0;
        }

        $activePlanetSummary = [
            'planet' => $selectedPlanet,
            'resources' => $this->summarizePlanetResources($selectedPlanet),
        ];

        return $this->render('fleet/index.php', [
            'title' => 'Flotte',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'fleetOverview' => [
                'ships' => $fleetShips,
                'totalShips' => $totalShips,
                'power' => $totalPower,
                'origin' => $origin,
            ],
            'availableShips' => $availableShips,
            'catalogCategories' => $this->shipCatalog->groupedByCategory(),
            'submittedComposition' => $submittedComposition,
            'submittedDestination' => $submittedDestination,
            'planResult' => $planResult,
            'planErrors' => $planErrors,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_plan' => $this->generateCsrfToken('fleet_plan_' . $selectedId),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'fleet',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }
}
