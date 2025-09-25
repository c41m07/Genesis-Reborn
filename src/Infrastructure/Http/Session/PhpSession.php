<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Session;

class PhpSession extends Session
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        parent::__construct($_SESSION);
    }

    public function invalidate(): void
    {
        parent::invalidate();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            session_destroy();
        }

        $_SESSION = $this->toArray();
    }
}
