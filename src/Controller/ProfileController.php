<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\Profile\GetProfileOverview;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly GetProfileOverview $getProfileOverview,
        ViewRenderer                             $renderer,
        SessionInterface                         $session,
        FlashBag                                 $flashBag,
        CsrfTokenManager                         $csrfTokenManager,
        string                                   $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function index(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->redirect($this->baseUrl . '/login');
        }

        $result = $this->getProfileOverview->execute($userId);

        return $this->render('pages/profile/index.php', [
            'title' => 'Profil commandant',
            'account' => $result['account'],
            'planets' => $result['planets'],
            'selectedPlanetId' => $result['selectedPlanetId'],
            'dashboard' => $result['dashboard'],
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'profile',
            'activePlanetSummary' => $result['activePlanetSummary'],
            'facilityStatuses' => $result['facilityStatuses'],
        ]);
    }
}
