<?php

namespace App\Controller;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Service\ResourceTickService;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeImmutable;

class ResourceApiController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly ProcessBuildQueue $buildQueue,
        private readonly ProcessResearchQueue $researchQueue,
        private readonly ProcessShipBuildQueue $shipQueue,
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly ResourceTickService $resourceTickService,
        ViewRenderer $renderer,
        SessionInterface $session,
        FlashBag $flashBag,
        CsrfTokenManager $csrfTokenManager,
        string $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function show(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
        }

        $planetId = (int) ($request->getQueryParams()['planet'] ?? 0);
        if ($planetId <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Planète invalide.',
            ], 400);
        }

        $planet = $this->planets->find($planetId);
        if (!$planet || $planet->getUserId() !== $userId) {
            return $this->json([
                'success' => false,
                'message' => 'Planète introuvable.',
            ], 404);
        }

        $previousBuildingLevels = $this->buildingStates->getLevels($planetId);

        $this->buildQueue->process($planetId);
        $this->researchQueue->process($planetId);
        $this->shipQueue->process($planetId);

        $planet = $this->planets->find($planetId) ?? $planet;

        $buildingLevels = $this->buildingStates->getLevels($planetId);
        $now = new DateTimeImmutable();

        $correctedFutureTick = false;
        $lastTick = $planet->getLastResourceTick();
        if ($lastTick > $now) {
            $planet->setLastResourceTick($now);
            $this->planets->update($planet);
            $lastTick = $now;
            $correctedFutureTick = true;
        }

        $tickStates = [
            $planetId => [
                'planet_id' => $planetId,
                'player_id' => $userId,
                'resources' => [
                    'metal' => $planet->getMetal(),
                    'crystal' => $planet->getCrystal(),
                    'hydrogen' => $planet->getHydrogen(),
                    'energy' => $planet->getEnergy(),
                ],
                'capacities' => [
                    'metal' => $planet->getMetalCapacity(),
                    'crystal' => $planet->getCrystalCapacity(),
                    'hydrogen' => $planet->getHydrogenCapacity(),
                    'energy' => $planet->getEnergyCapacity(),
                ],
                'last_tick' => $lastTick,
                'building_levels' => $buildingLevels,
                'previous_building_levels' => $previousBuildingLevels,
            ],
        ];

        $tickResults = $this->resourceTickService->tick($tickStates, $now);
        $planetTick = $tickResults[$planetId] ?? null;

        if ($planetTick !== null) {
            $elapsed = (int) $planetTick['elapsed_seconds'];

            /** @var array<string, int> $resources */
            $resources = $planetTick['resources'];
            if (isset($resources['metal'])) {
                $planet->setMetal((int) $resources['metal']);
            }
            if (isset($resources['crystal'])) {
                $planet->setCrystal((int) $resources['crystal']);
            }
            if (isset($resources['hydrogen'])) {
                $planet->setHydrogen((int) $resources['hydrogen']);
            }
            if (isset($resources['energy'])) {
                $planet->setEnergy((int) $resources['energy']);
            }

            /** @var array<string, int> $production */
            $production = $planetTick['production_per_hour'];
            if (isset($production['metal'])) {
                $planet->setMetalPerHour((int) $production['metal']);
            }
            if (isset($production['crystal'])) {
                $planet->setCrystalPerHour((int) $production['crystal']);
            }
            if (isset($production['hydrogen'])) {
                $planet->setHydrogenPerHour((int) $production['hydrogen']);
            }
            if (isset($production['energy'])) {
                $planet->setEnergyPerHour((int) $production['energy']);
            }

            /** @var array<string, int> $capacities */
            $capacities = $planetTick['capacities'];
            if (isset($capacities['metal'])) {
                $planet->setMetalCapacity((int) $capacities['metal']);
            }
            if (isset($capacities['crystal'])) {
                $planet->setCrystalCapacity((int) $capacities['crystal']);
            }
            if (isset($capacities['hydrogen'])) {
                $planet->setHydrogenCapacity((int) $capacities['hydrogen']);
            }
            if (isset($capacities['energy'])) {
                $planet->setEnergyCapacity((int) $capacities['energy']);
            }

            if ($elapsed > 0 || $correctedFutureTick) {
                $planet->setLastResourceTick($now);
                $this->planets->update($planet);
            }
        }

        return $this->json([
            'success' => true,
            'planetId' => $planetId,
            'resources' => $this->formatResourceSnapshot($planet),
        ]);
    }
}
