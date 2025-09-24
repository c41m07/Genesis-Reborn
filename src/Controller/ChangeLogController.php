<?php

namespace App\Controller;

use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Security\CsrfTokenManager;

class ChangeLogController extends AbstractController
{
    public function __construct(
        ViewRenderer $renderer,
        SessionInterface $session,
        FlashBag $flashBag,
        CsrfTokenManager $csrfTokenManager,
        string $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function index(): Response
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

        // Passage des variables à la vue, y compris currentUserId
        return $this->render('pages/changelog/index.php', [
            'title'         => 'Journal des versions',
            'changelog'     => $changelogData,
            'baseUrl'       => $this->baseUrl,
            'activeSection' => 'changelog',
            'currentUserId' => $userId,
            'flashes'       => $this->flashBag->consume(),
            'csrf_logout'   => $this->generateCsrfToken('logout'),
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
