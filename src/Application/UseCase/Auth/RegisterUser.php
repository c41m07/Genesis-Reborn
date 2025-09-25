<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Session\SessionInterface;
use Throwable;

class RegisterUser
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PlanetRepositoryInterface $planets,
        private readonly SessionInterface $session
    ) {
    }

    /** @return array{success: bool, message?: string} */
    public function execute(string $email, string $password, string $passwordConfirmation): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Adresse e-mail invalide.'];
        }

        if ($password !== $passwordConfirmation) {
            return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        if ($this->users->findByEmail($email)) {
            return ['success' => false, 'message' => 'Un compte existe déjà avec cet e-mail.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $user = $this->users->save($email, $hash);

        try {
            $this->planets->createHomeworld($user->getId());
        } catch (Throwable $exception) {
            return ['success' => false, 'message' => 'Impossible de créer la planète de départ.'];
        }

        $this->session->set('user_id', $user->getId());

        return ['success' => true];
    }
}
