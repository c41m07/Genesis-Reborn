<?php

namespace App\Controller;

use App\Domain\Entity\Planet;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Security\CsrfTokenManager;
use RuntimeException;

abstract class AbstractController
{
    public function __construct(
        protected readonly ViewRenderer $renderer,
        protected readonly SessionInterface $session,
        protected readonly FlashBag $flashBag,
        protected readonly CsrfTokenManager $csrfTokenManager,
        protected readonly string $baseUrl
    ) {
    }

    protected function render(string $template, array $parameters = [], int $status = 200): Response
    {
        $content = $this->renderer->render($template, $parameters);

        return new Response($content, $status);
    }

    protected function json(array $data, int $status = 200): Response
    {
        try {
            $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Impossible de sérialiser la réponse JSON.', 0, $exception);
        }

        return (new Response($payload, $status))->addHeader('Content-Type', 'application/json');
    }

    protected function redirect(string $path): Response
    {
        return (new Response('', 302))->addHeader('Location', $path);
    }

    protected function addFlash(string $type, string $message): void
    {
        $this->flashBag->add($type, $message);
    }

    protected function getUserId(): ?int
    {
        return $this->session->get('user_id');
    }

    protected function requireUser(): int
    {
        $userId = $this->getUserId();
        if (!$userId) {
            throw new \RuntimeException('Utilisateur non connecté.');
        }

        return (int) $userId;
    }

    protected function generateCsrfToken(string $id): string
    {
        return $this->csrfTokenManager->generateToken($id);
    }

    protected function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->csrfTokenManager->isTokenValid($id, $token);
    }

    protected function formatResourceSnapshot(Planet $planet): array
    {
        return [
            'metal' => [
                'value' => $planet->getMetal(),
                'perHour' => $planet->getMetalPerHour(),
                'capacity' => $planet->getMetalCapacity(),
            ],
            'crystal' => [
                'value' => $planet->getCrystal(),
                'perHour' => $planet->getCrystalPerHour(),
                'capacity' => $planet->getCrystalCapacity(),
            ],
            'hydrogen' => [
                'value' => $planet->getHydrogen(),
                'perHour' => $planet->getHydrogenPerHour(),
                'capacity' => $planet->getHydrogenCapacity(),
            ],
            'energy' => [
                'value' => $planet->getEnergy(),
                'perHour' => $planet->getEnergyPerHour(),
                'capacity' => $planet->getEnergyCapacity(),
            ],
        ];
    }
}
