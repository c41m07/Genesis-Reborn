<?php

namespace App\Controller;

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\ProcessShipBuildQueue;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;

class ResourceApiController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly ProcessBuildQueue $buildQueue,
        private readonly ProcessResearchQueue $researchQueue,
        private readonly ProcessShipBuildQueue $shipQueue,
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

        $this->buildQueue->process($planetId);
        $this->researchQueue->process($planetId);
        $this->shipQueue->process($planetId);

        $planet = $this->planets->find($planetId) ?? $planet;

        return $this->json([
            'success' => true,
            'planetId' => $planetId,
            'resources' => [
                'metal' => ['value' => $planet->getMetal(), 'perHour' => $planet->getMetalPerHour()],
                'crystal' => ['value' => $planet->getCrystal(), 'perHour' => $planet->getCrystalPerHour()],
                'hydrogen' => ['value' => $planet->getHydrogen(), 'perHour' => $planet->getHydrogenPerHour()],
                'energy' => ['value' => $planet->getEnergy(), 'perHour' => $planet->getEnergyPerHour()],
            ],
        ]);
    }
}
