<?php

namespace App\Application\UseCase\Auth;

use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Session\SessionInterface;

class LoginUser
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SessionInterface $session
    ) {
    }

    /** @return array{success: bool, message?: string} */
    public function execute(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, $user->getPasswordHash())) {
            return ['success' => false, 'message' => 'Identifiants invalides.'];
        }

        $this->session->set('user_id', $user->getId());

        return ['success' => true];
    }
}
