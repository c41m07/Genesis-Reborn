<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\UseCase\Auth\LoginUser;
use App\Application\UseCase\Auth\LogoutUser;
use App\Application\UseCase\Auth\RegisterUser;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly LoginUser $loginUser,
        private readonly LogoutUser $logoutUser,
        ViewRenderer $renderer,
        SessionInterface $session,
        FlashBag $flashBag,
        CsrfTokenManager $csrfTokenManager,
        string $baseUrl
    ) {
        parent::__construct($renderer, $session, $flashBag, $csrfTokenManager, $baseUrl);
    }

    public function login(Request $request): Response
    {
        if ($this->getUserId()) {
            return $this->redirect($this->baseUrl . '/dashboard');
        }

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            if (!$this->isCsrfTokenValid('login', $data['csrf_token'] ?? null)) {
                $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
                return $this->redirect($this->baseUrl . '/login');
            }
            $result = $this->loginUser->execute($data['email'] ?? '', $data['password'] ?? '');
            if ($result['success']) {
                return $this->redirect($this->baseUrl . '/dashboard');
            }

            $this->addFlash('danger', $result['message'] ?? 'Connexion impossible.');
        }

        return $this->render('pages/auth/login.php', [
            'title' => 'Connexion',
            'flashes' => $this->flashBag->consume(),
            'csrf_token' => $this->generateCsrfToken('login'),
            'baseUrl' => $this->baseUrl,
            'currentUserId' => $this->getUserId(),
        ]);
    }

    public function register(Request $request): Response
    {
        if ($this->getUserId()) {
            return $this->redirect($this->baseUrl . '/dashboard');
        }

        if ($request->getMethod() === 'POST') {
            $data = $request->getBodyParams();
            if (!$this->isCsrfTokenValid('register', $data['csrf_token'] ?? null)) {
                $this->addFlash('danger', 'Session expirée, veuillez réessayer.');
                return $this->redirect($this->baseUrl . '/register');
            }
            $result = $this->registerUser->execute(
                $data['email'] ?? '',
                $data['password'] ?? '',
                $data['password_confirm'] ?? ''
            );

            if ($result['success']) {
                $this->addFlash('success', 'Bienvenue commandant !');
                return $this->redirect($this->baseUrl . '/dashboard');
            }

            $this->addFlash('danger', $result['message'] ?? 'Inscription impossible.');
        }

        return $this->render('pages/auth/register.php', [
            'title' => 'Inscription',
            'flashes' => $this->flashBag->consume(),
            'csrf_token' => $this->generateCsrfToken('register'),
            'baseUrl' => $this->baseUrl,
            'currentUserId' => $this->getUserId(),
        ]);
    }

    public function logout(Request $request): Response
    {
        if ($request->getMethod() !== 'POST') {
            return new Response('Méthode non autorisée', 405);
        }

        $token = $request->getBodyParams()['csrf_token'] ?? null;
        if (!$this->isCsrfTokenValid('logout', $token)) {
            return new Response('Jeton CSRF invalide', 400);
        }

        $this->logoutUser->execute();

        return $this->redirect($this->baseUrl . '/login');
    }
}
