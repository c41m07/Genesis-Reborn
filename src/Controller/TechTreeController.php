<?php

namespace App\Controller;

use App\Application\UseCase\Research\GetTechTree;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;

class TechTreeController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface $planets,
        private readonly GetTechTree $getTechTree,
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
            return $this->render('pages/tech-tree/index.php', [
                'title' => 'Arbre technologique',
                'planets' => [],
                'tree' => ['groups' => []],
                'flashes' => $this->flashBag->consume(),
                'baseUrl' => $this->baseUrl,
                'currentUserId' => $userId,
                'activeSection' => 'tech-tree',
                'selectedPlanetId' => null,
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

        $tree = $this->getTechTree->execute($selectedId);
        $buildingLevels = $tree['buildingLevels'] ?? [];
        $facilityStatuses = [
            'research_lab' => ($buildingLevels['research_lab'] ?? 0) > 0,
            'shipyard' => ($buildingLevels['shipyard'] ?? 0) > 0,
        ];
        $activePlanetSummary = [
            'planet' => $selectedPlanet,
            'resources' => [
                'metal' => ['value' => $selectedPlanet->getMetal(), 'perHour' => $selectedPlanet->getMetalPerHour()],
                'crystal' => ['value' => $selectedPlanet->getCrystal(), 'perHour' => $selectedPlanet->getCrystalPerHour()],
                'hydrogen' => ['value' => $selectedPlanet->getHydrogen(), 'perHour' => $selectedPlanet->getHydrogenPerHour()],
                'energy' => ['value' => $selectedPlanet->getEnergy(), 'perHour' => $selectedPlanet->getEnergyPerHour()],
            ],
        ];

        return $this->render('pages/tech-tree/index.php', [
            'title' => 'Arbre technologique',
            'planets' => $planets,
            'selectedPlanetId' => $selectedId,
            'tree' => $tree,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'tech-tree',
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }
}
