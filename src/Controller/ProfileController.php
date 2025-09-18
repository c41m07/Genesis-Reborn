<?php

namespace App\Controller;

use App\Application\UseCase\Dashboard\GetDashboard;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use RuntimeException;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
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

        $user = $this->users->find($userId);
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        $dashboard = $this->getDashboard->execute($userId);
        $planetSummaries = $dashboard['planets'] ?? [];
        $planetList = array_map(static fn (array $summary) => $summary['planet'], $planetSummaries);

        $selectedPlanetId = null;
        $activePlanetSummary = null;
        if ($planetSummaries !== []) {
            $selectedPlanetId = $planetSummaries[0]['planet']->getId();
            $activePlanetSummary = [
                'planet' => $planetSummaries[0]['planet'],
                'resources' => [
                    'metal' => ['value' => $planetSummaries[0]['planet']->getMetal(), 'perHour' => $planetSummaries[0]['production']['metal']],
                    'crystal' => ['value' => $planetSummaries[0]['planet']->getCrystal(), 'perHour' => $planetSummaries[0]['production']['crystal']],
                    'hydrogen' => ['value' => $planetSummaries[0]['planet']->getHydrogen(), 'perHour' => $planetSummaries[0]['production']['hydrogen']],
                    'energy' => ['value' => $planetSummaries[0]['planet']->getEnergy(), 'perHour' => $planetSummaries[0]['production']['energy']],
                ],
            ];
        }

        return $this->render('profile/index.php', [
            'title' => 'Profil commandant',
            'account' => [
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
            ],
            'planets' => $planetList,
            'selectedPlanetId' => $selectedPlanetId,
            'dashboard' => $dashboard,
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'profile',
            'activePlanetSummary' => $activePlanetSummary,
        ]);
    }
}
