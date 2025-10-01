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
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'activeSection' => 'fleet',
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
                'activeMissions' => [],
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
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'fleet',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
            'activeMissions' => $activeMissions,
        ]);
    }
}
