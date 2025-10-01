<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\Resource\GetResourceSnapshot;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;

class ResourceApiController extends AbstractController
{
    public function __construct(
        private readonly GetResourceSnapshot $getResourceSnapshot,
        ViewRenderer                                      $renderer,
        SessionInterface                                  $session,
        FlashBag                                          $flashBag,
        CsrfTokenManager                                  $csrfTokenManager,
        string                                            $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function show(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
        }

        $planetId = (int)($request->getQueryParams()['planet'] ?? 0);
        $result = $this->getResourceSnapshot->execute($userId, $planetId);

        $payload = $result->getPayload();
        $planet = $result->getPlanet();
        if ($planet) {
            $payload['resources'] = $this->formatResourceSnapshot($planet);
        }

        return $this->json($payload, $result->getStatusCode());
    }
}
