<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Infrastructure\Http\Session\SessionInterface;

class LogoutUser
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function execute(): void
    {
        $this->session->invalidate();
    }
}
