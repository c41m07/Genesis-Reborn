<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\Journal\GetJournalOverview;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;

class JournalController extends AbstractController
{
    public function __construct(
        private readonly GetJournalOverview $getJournalOverview,
        ViewRenderer                                       $renderer,
        SessionInterface                                   $session,
        FlashBag                                           $flashBag,
        CsrfTokenManager                                   $csrfTokenManager,
        string                                             $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function index(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->redirect($this->baseUrl . '/login');
        }

        $result = $this->getJournalOverview->execute($userId, $request->getQueryParams());

        foreach ($result['messages'] as $message) {
            $this->addFlash($message['type'], $message['message']);
        }

        return $this->render('pages/journal/index.php', [
            'title' => 'Journal de bord',
            'planets' => $result['planets'],
            'selectedPlanetId' => $result['selectedPlanetId'],
            'events' => $result['events'],
            'insights' => $result['insights'],
            'flashes' => $this->flashBag->consume(),
            'baseUrl' => $this->baseUrl,
            'csrf_logout' => $this->generateCsrfToken('logout'),
            'currentUserId' => $userId,
            'activeSection' => 'journal',
            'activePlanetSummary' => $result['activePlanetSummary'],
            'facilityStatuses' => $result['facilityStatuses'],
        ]);
    }
}
