<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Http\Session\SessionInterface;

class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_tokens';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function generateToken(string $id): string
    {
        $token = bin2hex(random_bytes(32));
        $tokens = $this->session->get(self::SESSION_KEY, []);
        $tokens[$id] = $token;
        $this->session->set(self::SESSION_KEY, $tokens);

        return $token;
    }

    public function isTokenValid(string $id, ?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        $tokens = $this->session->get(self::SESSION_KEY, []);
        $stored = $tokens[$id] ?? null;

        return is_string($stored) && hash_equals($stored, $token);
    }
}
