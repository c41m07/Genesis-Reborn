<?php

namespace App\Controller;

use App\Infrastructure\Http\Response;

class ChangeLogController extends AbstractController
{
    /**
     * Point d’entrée de la page changelog.
     * Charge les données du journal des versions et renvoie la vue associée.
     */
    public function index(): Response
    {
        // Chemin corrigé vers le JSON hébergé en public/
        $filePath = dirname(__DIR__, 3) . '/public/data/changelog.json';
        $changelogData = [];

        if (is_readable($filePath)) {
            try {
                $json = file_get_contents($filePath);
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $changelogData = $decoded;
                }
            } catch (\Throwable $e) {
                // En cas d'erreur JSON, on ignore et on affiche un état vide.
                $changelogData = [];
            }
        }

        // Rendu de la vue. Adaptez le chemin du template à votre structure.
        return $this->render('pages/changelog/index.php', [
            'changelog' => $changelogData,
            'title'     => 'Journal des versions',
            'baseUrl'   => $this->baseUrl,
        ]);
    }

    public function api(): Response
    {
        // Lecture robuste et unique du même fichier
        $filePath = dirname(__DIR__, 3) . '/public/data/changelog.json';
        $data = [];

        if (is_readable($filePath)) {
            try {
                $json = file_get_contents($filePath);
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (\Throwable $e) {
                $data = [];
            }
        }

        return $this->json($data);
    }
}
