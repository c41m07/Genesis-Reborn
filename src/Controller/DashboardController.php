<?php

namespace App\Controller;

use App\Application\UseCase\Dashboard\GetDashboard;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly GetDashboard $getDashboard,
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

        $data = $this->getDashboard->execute($userId);

        $planets = array_map(static fn (array $summary) => $summary['planet'], $data['planets']);
        if (empty($planets)) {
            return $this->render('pages/dashboard/index.php', [
                'title' => 'Vue d’ensemble',
                'dashboard' => $data,
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'planets' => [],
                'activeSection' => 'dashboard',
                'selectedPlanetId' => null,
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
            ]);
        }

        $selectedId = (int) ($request->getQueryParams()['planet'] ?? $planets[0]->getId());
        $activeSummary = $data['planets'][0] ?? null;

        foreach ($data['planets'] as $summary) {
            if ($summary['planet']->getId() === $selectedId) {
                $activeSummary = $summary;
                break;
            }
        }

        if ($activeSummary === null) {
            return $this->render('pages/dashboard/index.php', [
                'title' => 'Vue d’ensemble',
                'dashboard' => $data,
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'csrf_logout' => $this->generateCsrfToken('logout'),
                'currentUserId' => $userId,
                'planets' => $planets,
                'activeSection' => 'dashboard',
                'selectedPlanetId' => null,
                'activePlanetSummary' => null,
                'facilityStatuses' => [],
            ]);
        }

        $planet = $activeSummary['planet'];
        $production = $activeSummary['production'];
        $levels = $activeSummary['levels'] ?? [];
        $facilityStatuses = [
            'research_lab' => ($levels['research_lab'] ?? 0) > 0,
            'shipyard' => ($levels['shipyard'] ?? 0) > 0,
        ];

        $activePlanetSummary = [
            'planet' => $planet,
            'resources' => $this->summarizePlanetResources($planet, $production),
        ];

        return $this->render('pages/dashboard/index.php', [
            'title' => 'Vue d’ensemble',
            'dashboard' => $data,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'planets' => $planets,
            'activeSection' => 'dashboard',
            'selectedPlanetId' => $planet->getId(),
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }
}
