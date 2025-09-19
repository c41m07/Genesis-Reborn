<?php

namespace App\Controller;

use App\Application\Service\ProcessResearchQueue;
use App\Application\UseCase\Research\GetResearchOverview;
use App\Application\UseCase\Research\StartResearch;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;

class ResearchController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly GetResearchOverview $getOverview,
        private readonly StartResearch $startResearch,
        private readonly ProcessResearchQueue $researchQueueProcessor,
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

            return $this->render('research/index.php', [
                'title' => 'Recherche',
                'planets' => [],
                'overview' => null,
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'currentUserId' => $userId,
                'activeSection' => 'research',
                'selectedPlanetId' => null,
                'activePlanetSummary' => null,
                'csrf_logout' => $this->generateCsrfToken('logout'),
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

        $this->researchQueueProcessor->process($selectedId);

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            if (!$this->isCsrfTokenValid('start_research_' . $selectedId, $data['csrf_token'] ?? null)) {
                $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
                return $this->redirect($this->baseUrl . '/research?planet=' . $selectedId);
            }

            $result = $this->startResearch->execute($selectedId, $userId, $data['research'] ?? '');
            if ($result['success']) {
                $this->addFlash('success', $result['message'] ?? 'Recherche planifiée.');
            } else {
                $this->addFlash('danger', $result['message'] ?? 'Action impossible.');
            }

            return $this->redirect($this->baseUrl . '/research?planet=' . $selectedId);
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

        return $this->render('research/index.php', [
            'title' => 'Recherche',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'overview' => $overview,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_start' => $this->generateCsrfToken('start_research_' . $selectedId),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'research',
            'activePlanetSummary' => $activePlanetSummary,
        ]);
    }
}
