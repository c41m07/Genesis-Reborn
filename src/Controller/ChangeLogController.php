<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;

class ChangeLogController extends AbstractController
{
    public function __construct(
        private readonly PlanetRepositoryInterface        $planets,
        private readonly BuildingStateRepositoryInterface $buildingStates,
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
        // Vérifie que l’utilisateur est connecté
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->redirect($this->baseUrl . '/login');
        }

        // Lecture du fichier JSON
        $filePath = dirname(__DIR__, 3) . '/public/data/changelog.json';
        $changelogData = [];
        if (is_readable($filePath)) {
            try {
                $json = file_get_contents($filePath);
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $changelogData = $decoded;
                }
            } catch (\Throwable) {
                // En cas d’erreur JSON, on garde un tableau vide
            }
        }

        $planets = $this->planets->findByUser($userId);
        $selectedPlanetId = null;
        $activePlanetSummary = null;
        $facilityStatuses = [];

        if ($planets !== []) {
            $selectedPlanetId = (int)($request->getQueryParams()['planet'] ?? $planets[0]->getId());
            $selectedPlanet = $planets[0];
            foreach ($planets as $planet) {
                if ($planet->getId() === $selectedPlanetId) {
                    $selectedPlanet = $planet;
                    break;
                }
            }

            $selectedPlanetId = $selectedPlanet->getId();

            $buildingLevels = $this->buildingStates->getLevels($selectedPlanetId);
            $facilityStatuses = [
                'research_lab' => ($buildingLevels['research_lab'] ?? 0) > 0,
                'shipyard' => ($buildingLevels['shipyard'] ?? 0) > 0,
            ];

            $activePlanetSummary = [
                'planet' => $selectedPlanet,
                'resources' => $this->formatResourceSnapshot($selectedPlanet),
            ];
        }


        // Passage des variables à la vue, y compris currentUserId
        return $this->render('pages/changelog/index.php', [
            'title' => 'Journal des versions',
            'changelog' => $changelogData,
            'baseUrl' => $this->baseUrl,
            'activeSection' => 'changelog',
            'currentUserId' => $userId,
            'flashes' => $this->flashBag->consume(),
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'planets' => $planets,
            'selectedPlanetId' => $selectedPlanetId,
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ]);
    }

    public function api(): Response
    {
        // Lecture identique du JSON pour l’API
        $filePath = dirname(__DIR__, 3) . '/public/data/changelog.json';
        $data = [];
        if (is_readable($filePath)) {
            try {
                $json = file_get_contents($filePath);
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (\Throwable) {
                // On ignore l’erreur et renvoie un tableau vide
            }
        }

        return $this->json($data);
    }
}
