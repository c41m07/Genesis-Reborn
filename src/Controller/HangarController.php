<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Fleet\AssembleFleetFromHangar;
use App\Application\UseCase\Fleet\MergeIdleFleets;
use App\Application\UseCase\Fleet\RenameIdleFleet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\HangarRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use InvalidArgumentException;

final class HangarController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface        $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly HangarRepositoryInterface        $hangars,
        private readonly FleetRepositoryInterface         $fleets,
        private readonly ShipCatalog                      $shipCatalog,
        private readonly ProcessShipBuildQueue            $shipQueueProcessor,
        private readonly AssembleFleetFromHangar          $assembleFleet,
        private readonly RenameIdleFleet                  $renameFleet,
        private readonly MergeIdleFleets                  $mergeFleets,
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

            return $this->render('pages/hangar/index.php', [
                'title' => 'Hangar planétaire',
                'planets' => [],
                'selectedPlanetId' => null,
                'hangarEntries' => [],
                'totalShips' => 0,
                'idleFleets' => [],
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'csrf_transfer' => null,
                'currentUserId' => $userId,
                'activeSection' => 'hangar',
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
                'transferErrors' => [],
                'submittedTransfer' => [
                    'ships' => [],
                    'mode' => 'existing',
                    'fleet_id' => '',
                    'new_fleet_name' => '',
                ],
            ]);
        }

        $selectedId = (int)($request->getQueryParams()['planet'] ?? $planets[0]->getId());
        $selectedPlanet = current(array_filter($planets, fn($planet) => $planet->getId() === $selectedId)) ?: $planets[0];

        $this->shipQueueProcessor->process($selectedId);

        $fleetSummaries = $this->fleets->listIdleFleets($selectedId);
        $fleetOptions = [];
        $renameableFleets = [];
        $availableShipKeys = [];
        $defaultFleetId = null;
        foreach ($fleetSummaries as $summary) {
            $label = $summary['name'] ?? 'Platforme orbitale';
            if ($summary['is_garrison']) {
                $label = 'Platforme orbitale';
                $defaultFleetId = $summary['id'];
            }

            $fleetOptions[] = [
                'id' => $summary['id'],
                'label' => $label,
                'total' => $summary['total'],
                'is_garrison' => $summary['is_garrison'],
            ];

            if (!$summary['is_garrison']) {
                $renameableFleets[] = [
                    'id' => $summary['id'],
                    'label' => $summary['name'] ?? sprintf('Flotte #%d', $summary['id']),
                ];
            }

            foreach (array_keys($summary['ships']) as $shipKey) {
                $availableShipKeys[$shipKey] = true;
            }
        }
        $availableShipKeys = array_keys($availableShipKeys);

        $buildingLevels = $this->buildingStates->getLevels($selectedId);
        $facilityStatuses = [
            'research_lab' => ($buildingLevels['research_lab'] ?? 0) > 0,
            'shipyard' => ($buildingLevels['shipyard'] ?? 0) > 0,
        ];

        if (!$facilityStatuses['shipyard']) {
            $message = 'Un chantier spatial est requis pour accéder au hangar.';
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

        $transferErrors = [];
        $renameErrors = [];
        $mergeErrors = [];
        $submittedTransfer = [
            'ships' => [],
            'mode' => 'existing',
            'fleet_id' => $defaultFleetId !== null ? (string)$defaultFleetId : '',
            'new_fleet_name' => '',
        ];
        $submittedRename = [
            'fleet_id' => '',
            'new_name' => '',
        ];
        $submittedMerge = [
            'source_id' => '',
            'target_id' => '',
            'mode' => 'partial',
            'ship_key' => '',
            'quantity' => 0,
        ];

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            $csrfKey = 'hangar_manage_' . $selectedId;
            $action = (string)($data['action'] ?? 'transfer');

            if (!$this->isCsrfTokenValid($csrfKey, $data['csrf_token'] ?? null)) {
                if ($action === 'rename') {
                    $renameErrors[] = 'Session expirée, veuillez réessayer.';
                } elseif ($action === 'merge') {
                    $mergeErrors[] = 'Session expirée, veuillez réessayer.';
                } else {
                    $transferErrors[] = 'Session expirée, veuillez réessayer.';
                }
            } else {
                switch ($action) {
                    case 'rename':
                        $fleetId = isset($data['fleet_id']) ? (int)$data['fleet_id'] : 0;
                        $newName = (string)($data['new_name'] ?? '');
                        $submittedRename = [
                            'fleet_id' => $fleetId > 0 ? (string)$fleetId : '',
                            'new_name' => $newName,
                        ];

                        if ($fleetId <= 0) {
                            $renameErrors[] = 'Veuillez sélectionner une flotte à renommer.';
                            break;
                        }

                        $result = $this->renameFleet->execute($userId, $selectedId, $fleetId, $newName);
                        if ($result['success']) {
                            $this->addFlash('success', $result['message']);

                            return $this->redirect($this->baseUrl . '/hangar?planet=' . $selectedId);
                        }

                        $renameErrors[] = $result['message'];
                        break;

                    case 'merge':
                        $sourceId = isset($data['source_id']) ? (int)$data['source_id'] : 0;
                        $targetId = isset($data['target_id']) ? (int)$data['target_id'] : 0;
                        $mode = (string)($data['merge_mode'] ?? 'partial');
                        $shipKey = (string)($data['ship_key'] ?? '');
                        $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;
                        $submittedMerge = [
                            'source_id' => $sourceId > 0 ? (string)$sourceId : '',
                            'target_id' => $targetId > 0 ? (string)$targetId : '',
                            'mode' => in_array($mode, ['all', 'partial'], true) ? $mode : 'partial',
                            'ship_key' => $shipKey,
                            'quantity' => $quantity,
                        ];

                        if ($sourceId <= 0 || $targetId <= 0) {
                            $mergeErrors[] = 'Veuillez sélectionner les deux flottes à fusionner.';
                            break;
                        }

                        $quantityParam = $submittedMerge['mode'] === 'all' ? null : $quantity;
                        $shipKeyParam = $submittedMerge['mode'] === 'all' ? null : $shipKey;

                        $result = $this->mergeFleets->execute(
                            $userId,
                            $selectedId,
                            $sourceId,
                            $targetId,
                            $submittedMerge['mode'],
                            $shipKeyParam,
                            $quantityParam
                        );

                        if ($result['success']) {
                            $this->addFlash('success', $result['message']);

                            return $this->redirect($this->baseUrl . '/hangar?planet=' . $selectedId);
                        }

                        $mergeErrors[] = $result['message'];
                        break;

                    default:
                        $rawQuantities = $data['ships'] ?? [];
                        if (!is_array($rawQuantities)) {
                            $rawQuantities = [];
                        }

                        $normalizedQuantities = [];
                        $positiveQuantities = [];
                        foreach ($rawQuantities as $shipKey => $value) {
                            $key = (string)$shipKey;
                            $quantityValue = (int)$value;
                            if ($quantityValue < 0) {
                                $quantityValue = 0;
                            }

                            $normalizedQuantities[$key] = $quantityValue;

                            if ($quantityValue > 0) {
                                $positiveQuantities[$key] = $quantityValue;
                            }
                        }

                        $mode = (string)($data['target_mode'] ?? 'existing');
                        $fleetId = isset($data['fleet_id']) && $data['fleet_id'] !== '' ? (string)$data['fleet_id'] : '';
                        $newFleetName = (string)($data['new_fleet_name'] ?? '');
                        $submittedTransfer = [
                            'ships' => $normalizedQuantities,
                            'mode' => in_array($mode, ['existing', 'new'], true) ? $mode : 'existing',
                            'fleet_id' => $fleetId,
                            'new_fleet_name' => $newFleetName,
                        ];

                        if ($positiveQuantities === []) {
                            $transferErrors[] = 'Veuillez saisir une quantité positive pour au moins un vaisseau.';
                            break;
                        }

                        $selectedFleetId = null;
                        if ($submittedTransfer['mode'] === 'existing' && $submittedTransfer['fleet_id'] !== '') {
                            $selectedFleetId = (int)$submittedTransfer['fleet_id'];
                        }

                        $newFleetNameValue = $submittedTransfer['mode'] === 'new' ? $submittedTransfer['new_fleet_name'] : null;

                        $resultMessage = null;
                        foreach ($positiveQuantities as $shipKey => $quantity) {
                            $result = $this->assembleFleet->execute(
                                $userId,
                                $selectedId,
                                $shipKey,
                                $quantity,
                                $selectedFleetId,
                                $newFleetNameValue
                            );

                            if (!$result['success']) {
                                $transferErrors[] = $result['message'];

                                break 2;
                            }

                            $resultMessage = $result['message'];
                        }

                        if ($resultMessage !== null) {
                            $this->addFlash('success', $resultMessage);

                            return $this->redirect($this->baseUrl . '/hangar?planet=' . $selectedId);
                        }
                        break;
                }
            }
        }

        $stock = $this->hangars->getStock($selectedId);
        $entries = [];
        $totalShips = 0;
        foreach ($stock as $shipKey => $quantity) {
            $definition = null;
            try {
                $definition = $this->shipCatalog->get($shipKey);
            } catch (InvalidArgumentException $exception) {
                $definition = null;
            }

            $label = $definition ? $definition->getLabel() : $shipKey;
            $description = $definition ? $definition->getDescription() : '';
            $stats = $definition ? $definition->getStats() : [];
            $role = $definition ? $definition->getRole() : '';
            $image = $definition ? $definition->getImage() : null;

            $entries[] = [
                'key' => $shipKey,
                'label' => $label,
                'quantity' => (int)$quantity,
                'description' => $description,
                'stats' => $stats,
                'role' => $role,
                'image' => $image,
            ];

            $totalShips += (int)$quantity;
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $planet = $selectedPlanet;
        $activePlanetSummary = [
            'planet' => $planet,
            'resources' => $this->formatResourceSnapshot($planet),
        ];

        return $this->render('pages/hangar/index.php', [
            'title' => 'Hangar planétaire',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'hangarEntries' => $entries,
            'totalShips' => $totalShips,
            'idleFleets' => $fleetOptions,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_transfer' => $this->generateCsrfToken('hangar_manage_' . $selectedId),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'hangar',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
            'transferErrors' => $transferErrors,
            'submittedTransfer' => $submittedTransfer,
            'renameErrors' => $renameErrors,
            'submittedRename' => $submittedRename,
            'mergeErrors' => $mergeErrors,
            'submittedMerge' => $submittedMerge,
            'idleFleetSummaries' => $fleetSummaries,
            'availableFleetShipKeys' => $availableShipKeys,
            'renameableFleets' => $renameableFleets,
        ]);
    }
}
