<?php

namespace App\Controller;

use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Shipyard\BuildShips;
use App\Application\UseCase\Shipyard\GetShipyardOverview;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;

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

        if ($selectedPlanet) {
            $this->shipQueueProcessor->process($selectedId);
        }

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            if (!$this->isCsrfTokenValid('shipyard_' . $selectedId, $data['csrf_token'] ?? null)) {
                $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
                return $this->redirect($this->baseUrl . '/shipyard?planet=' . $selectedId);
            }

            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;
            $quantity = max(1, $quantity);
            $result = $this->buildShips->execute($selectedId, $userId, $data['ship'] ?? '', $quantity);
            if ($result['success']) {
                $this->addFlash('success', $result['message'] ?? 'Production planifiée.');
            } else {
                $this->addFlash('danger', $result['message'] ?? 'Action impossible.');
            }

            return $this->redirect($this->baseUrl . '/shipyard?planet=' . $selectedId);
        }

        $overview = $this->getOverview->execute($selectedId);
        $planet = $overview['planet'];
        $activePlanetSummary = [
            'planet' => $planet,
            'resources' => [
                'metal' => ['value' => $planet->getMetal(), 'perHour' => $planet->getMetalPerHour()],
                'crystal' => ['value' => $planet->getCrystal(), 'perHour' => $planet->getCrystalPerHour()],
                'hydrogen' => ['value' => $planet->getHydrogen(), 'perHour' => $planet->getHydrogenPerHour()],
                'energy' => ['value' => $planet->getEnergy(), 'perHour' => $planet->getEnergyPerHour()],
            ],
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
        ]);
    }
}
