<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Fleet\PlanFleetMission;
use App\Application\UseCase\Fleet\ProcessFleetArrivals;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
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
        private readonly PlanetRepositoryInterface        $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly FleetRepositoryInterface         $fleets,
        private readonly FleetMovementRepositoryInterface $movements,
        private readonly ShipCatalog                      $shipCatalog,
        private readonly ProcessShipBuildQueue            $shipQueueProcessor,
        private readonly PlanFleetMission                 $planFleetMission,
        private readonly ProcessFleetArrivals             $processFleetArrivals,
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

        $planets = $this->planets->findByUser($userId);
        if ($planets === []) {
            $this->addFlash('info', 'Aucune planète disponible.');

            return $this->render('pages/fleet/index.php', [
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
                'csrf_launch' => $this->generateCsrfToken('fleet_launch_0'),
                'csrf_create' => $this->generateCsrfToken('fleet_create_0'),
                'csrf_transfer' => $this->generateCsrfToken('fleet_transfer_0'),
                'csrf_rename' => $this->generateCsrfToken('fleet_rename_0'),
                'csrf_delete' => $this->generateCsrfToken('fleet_delete_0'),
                'csrf_manage_mission' => $this->generateCsrfToken('fleet_mission_0'),
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'activeSection' => 'fleet',
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
                'activeMissions' => [],
                'idleFleets' => [],
                'selectedFleetId' => null,
                'selectedFleet' => null,
                'garrisonFleetId' => null,
            ]);
        }

        $selectedId = (int)($request->getQueryParams()['planet'] ?? $planets[0]->getId());
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

        if (!$facilityStatuses['shipyard']) {
            $message = 'Pour gérer votre flotte, vous devez construire un chantier spatial.';
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

        $this->processFleetArrivals->execute($userId, new DateTimeImmutable());

        $fleet = $this->fleets->getFleet($selectedId);
        $fleetShips = [];
        $availableShips = [];
        $totalShips = 0;
        $totalPower = 0;

        foreach ($fleet as $shipKey => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity <= 0) {
                continue;
            }

            $definition = null;
            try {
                $definition = $this->shipCatalog->get($shipKey);
            } catch (InvalidArgumentException $exception) {
                // Je note ici que le vaisseau est inconnu, du coup je garde juste les infos minimales.
            }

            $label = $definition ? $definition->getLabel() : $shipKey;
            $stats = $definition ? $definition->getStats() : [];
            $attack = (int)($stats['attaque'] ?? 0);
            $defense = (int)($stats['défense'] ?? 0);
            $speed = (int)($stats['vitesse'] ?? 0);
            $category = $definition ? $definition->getCategory() : 'Divers';
            $role = $definition ? $definition->getRole() : '';
            $image = $definition ? $definition->getImage() : null;
            $fuelRate = 0;
            if ($definition) {
                $baseCost = $definition->getBaseCost();
                $fuelRate = (int)max(1, ceil(($baseCost['hydrogen'] ?? 0) / 25));
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
        }

        usort($fleetShips, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);
        usort($availableShips, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $idleFleetSummaries = $this->fleets->listIdleFleets($selectedId);
        $idleFleets = [];
        $garrisonFleetId = null;
        foreach ($idleFleetSummaries as $summary) {
            $id = (int)($summary['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $isGarrison = (bool)($summary['is_garrison'] ?? false);
            if ($isGarrison) {
                $garrisonFleetId = $id;
            }

            $name = $summary['name'] ?? null;
            $label = $name ?: ($isGarrison ? 'Garnison orbitale' : 'Flotte #' . $id);

            $ships = [];
            $rawShips = is_array($summary['ships'] ?? null) ? $summary['ships'] : [];
            foreach ($rawShips as $shipKey => $quantity) {
                $quantity = (int)$quantity;
                if ($quantity <= 0) {
                    continue;
                }

                $definition = null;
                try {
                    $definition = $this->shipCatalog->get((string)$shipKey);
                } catch (InvalidArgumentException) {
                    // On ignore l'exception : le vaisseau peut avoir été retiré du catalogue.
                }

                $ships[] = [
                    'key' => (string)$shipKey,
                    'label' => $definition ? $definition->getLabel() : (string)$shipKey,
                    'quantity' => $quantity,
                    'role' => $definition ? $definition->getRole() : '',
                    'image' => $definition ? $definition->getImage() : null,
                ];
            }

            usort($ships, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

            $idleFleets[] = [
                'id' => $id,
                'name' => $name,
                'label' => $label,
                'total' => (int)($summary['total'] ?? 0),
                'is_garrison' => $isGarrison,
                'ships' => $ships,
                'ships_raw' => array_map(static fn ($value): int => (int)$value, $rawShips),
            ];
        }

        $selectedFleetId = isset($request->getQueryParams()['fleet'])
            ? (int)$request->getQueryParams()['fleet']
            : null;
        if ($selectedFleetId !== null && $selectedFleetId <= 0) {
            $selectedFleetId = null;
        }

        $selectedFleet = null;
        if ($selectedFleetId !== null) {
            foreach ($idleFleets as $fleetSummary) {
                if ($fleetSummary['id'] === $selectedFleetId) {
                    $selectedFleet = $fleetSummary;
                    break;
                }
            }
        }

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
                $destinationInput = [
                    'galaxy' => (int)($data['destination_galaxy'] ?? $origin['galaxy']),
                    'system' => (int)($data['destination_system'] ?? $origin['system']),
                    'position' => (int)($data['destination_position'] ?? $origin['position']),
                ];

                $speedFactor = isset($data['speed_factor']) ? (float)$data['speed_factor'] : 1.0;
                if ($speedFactor > 1) {
                    $speedFactor /= 100;
                }

                $compositionInput = [];
                foreach ($availableShips as $ship) {
                    $compositionInput[$ship['key']] = (int)($data['composition'][$ship['key']] ?? 0);
                }

                $planResponse = $this->planFleetMission->execute(
                    $userId,
                    $selectedId,
                    $compositionInput,
                    $destinationInput,
                    $speedFactor,
                    (string)($data['mission'] ?? 'transport')
                );

                $submittedComposition = $planResponse['composition'];
                $submittedDestination = $planResponse['destination'];

                if ($planResponse['success']) {
                    $planResult = $planResponse['plan'];
                } else {
                    $planErrors = $planResponse['errors'];
                }
            }
        }

        foreach ($availableShips as $ship) {
            $submittedComposition[$ship['key']] = $submittedComposition[$ship['key']] ?? 0;
        }

        $activeMissions = array_map(
            static fn ($movement): array => [
                'id' => $movement->getId(),
                'mission' => $movement->getMission()->value,
                'status' => $movement->getStatus()->value,
                'destination' => $movement->getDestination()->toArray(),
                'arrivalAt' => $movement->getArrivalAt()?->format('d/m/Y H:i'),
            ],
            $this->movements->findActiveByOriginPlanet($selectedId)
        );

        $activePlanetSummary = [
            'planet' => $selectedPlanet,
            'resources' => [
                'metal' => ['value' => $selectedPlanet->getMetal(), 'perHour' => $selectedPlanet->getMetalPerHour()],
                'crystal' => ['value' => $selectedPlanet->getCrystal(), 'perHour' => $selectedPlanet->getCrystalPerHour()],
                'hydrogen' => ['value' => $selectedPlanet->getHydrogen(), 'perHour' => $selectedPlanet->getHydrogenPerHour()],
                'energy' => ['value' => $selectedPlanet->getEnergy(), 'perHour' => $selectedPlanet->getEnergyPerHour()],
            ],
        ];

        return $this->render('pages/fleet/index.php', [
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
            'csrf_launch' => $this->generateCsrfToken('fleet_launch_' . $selectedId),
            'csrf_create' => $this->generateCsrfToken('fleet_create_' . $selectedId),
            'csrf_transfer' => $this->generateCsrfToken('fleet_transfer_' . $selectedId),
            'csrf_rename' => $this->generateCsrfToken('fleet_rename_' . $selectedId),
            'csrf_delete' => $this->generateCsrfToken('fleet_delete_' . $selectedId),
            'csrf_manage_mission' => $this->generateCsrfToken('fleet_mission_' . $selectedId),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'fleet',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
            'activeMissions' => $activeMissions,
            'idleFleets' => $idleFleets,
            'selectedFleetId' => $selectedFleetId,
            'selectedFleet' => $selectedFleet,
            'garrisonFleetId' => $garrisonFleetId,
        ]);
    }
}
