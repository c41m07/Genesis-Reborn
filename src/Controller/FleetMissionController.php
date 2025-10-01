<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\Fleet\LaunchFleetMission;
use App\Application\UseCase\Fleet\PlanFleetMission;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use DateTimeImmutable;

class FleetMissionController extends AbstractController
{
    public function __construct(
        private readonly PlanFleetMission    $planFleetMission,
        private readonly LaunchFleetMission  $launchFleetMission,
        ViewRenderer                         $renderer,
        SessionInterface                     $session,
        FlashBag                             $flashBag,
        CsrfTokenManager                     $csrfTokenManager,
        string                               $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function plan(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->json(['success' => false, 'errors' => ['Authentification requise.']], 401);
        }

        $data = $this->getPayload($request);
        $originPlanetId = (int)($data['originPlanetId'] ?? $data['planetId'] ?? 0);

        if (!$this->isCsrfTokenValid('fleet_plan_' . $originPlanetId, $data['csrf_token'] ?? null)) {
            return $this->json(['success' => false, 'errors' => ['Jeton CSRF invalide.']], 419);
        }

        $composition = $this->extractComposition($data['composition'] ?? []);
        $destination = $this->extractDestination($data);
        $speedFactor = $this->extractSpeedFactor($data);
        $mission = (string)($data['mission'] ?? 'transport');

        $result = $this->planFleetMission->execute(
            $userId,
            $originPlanetId,
            $composition,
            $destination,
            $speedFactor,
            $mission
        );

        $plan = $result['plan'];
        if ($plan !== null && $plan['arrival_time'] instanceof DateTimeImmutable) {
            $plan['arrival_time'] = $plan['arrival_time']->format(DATE_ATOM);
        }

        $status = $result['success'] ? 200 : 422;

        return $this->json([
            'success' => $result['success'],
            'errors' => $result['errors'],
            'mission' => $result['mission'],
            'composition' => $result['composition'],
            'destination' => $result['destination'],
            'plan' => $plan,
        ], $status);
    }

    public function launch(Request $request): Response
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->json(['success' => false, 'errors' => ['Authentification requise.']], 401);
        }

        $data = $this->getPayload($request);
        $originPlanetId = (int)($data['originPlanetId'] ?? $data['planetId'] ?? 0);

        if (!$this->isCsrfTokenValid('fleet_launch_' . $originPlanetId, $data['csrf_token'] ?? null)) {
            return $this->json(['success' => false, 'errors' => ['Jeton CSRF invalide.']], 419);
        }

        $composition = $this->extractComposition($data['composition'] ?? []);
        $destination = $this->extractDestination($data);
        $speedFactor = $this->extractSpeedFactor($data);
        $mission = (string)($data['mission'] ?? 'transport');

        $result = $this->launchFleetMission->execute(
            $userId,
            $originPlanetId,
            $composition,
            $destination,
            $speedFactor,
            $mission
        );

        $status = $result['success'] ? 200 : 422;

        return $this->json([
            'success' => $result['success'],
            'errors' => $result['errors'],
            'mission' => $result['mission'] ?? null,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayload(Request $request): array
    {
        $body = $request->getBodyParams();
        if ($body !== []) {
            return $body;
        }

        $content = file_get_contents('php://input');
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{galaxy: int, system: int, position: int}
     */
    private function extractDestination(array $data): array
    {
        if (isset($data['destination']) && is_array($data['destination'])) {
            $destination = $data['destination'];
        } else {
            $destination = [
                'galaxy' => $data['destination_galaxy'] ?? null,
                'system' => $data['destination_system'] ?? null,
                'position' => $data['destination_position'] ?? null,
            ];
        }

        return [
            'galaxy' => max(1, (int)($destination['galaxy'] ?? 1)),
            'system' => max(1, (int)($destination['system'] ?? 1)),
            'position' => max(1, (int)($destination['position'] ?? 1)),
        ];
    }

    /**
     * @param array<string, int|numeric|string> $composition
     *
     * @return array<string, int>
     */
    private function extractComposition(array $composition): array
    {
        $sanitized = [];
        foreach ($composition as $key => $value) {
            $sanitized[(string)$key] = max(0, (int)$value);
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractSpeedFactor(array $data): float
    {
        $speed = $data['speedFactor'] ?? $data['speed_factor'] ?? 1.0;
        $speed = (float)$speed;

        if ($speed > 1) {
            $speed /= 100;
        }

        return max(0.1, min(1.0, $speed));
    }
}
