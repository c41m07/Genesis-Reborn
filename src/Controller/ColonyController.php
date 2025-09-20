<?php

namespace App\Controller;

use App\Application\Service\ProcessBuildQueue;
use App\Application\UseCase\Building\GetBuildingsOverview;
use App\Application\UseCase\Building\UpgradeBuilding;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;

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

            return $this->render('colony/index.php', [
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

                return $this->json([
                    'success' => $result['success'],
                    'message' => $result['message'] ?? ($result['success'] ? 'Construction planifiée.' : 'Action impossible.'),
                    'resources' => $this->formatResourceSnapshot($planet),
                    'queue' => $overview['queue'],
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
            'resources' => $this->summarizePlanetResources($planet),
        ];

        return $this->render('colony/index.php', [
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

    private function formatResourceSnapshot(\App\Domain\Entity\Planet $planet): array
    {
        return $this->summarizePlanetResources($planet);
    }
}
