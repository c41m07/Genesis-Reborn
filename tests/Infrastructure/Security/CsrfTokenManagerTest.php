<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Http\Session\Session;
use App\Infrastructure\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

final class CsrfTokenManagerTest extends TestCase
{
    public function testGenerateTokenStoresTokenInSession(): void
    {
        $sessionData = [];
        $session = new Session($sessionData);
        $manager = new CsrfTokenManager($session);

        $token = $manager->generateToken('form_login');

        self::assertNotSame('', $token);
        self::assertSame(64, strlen($token));

        $stored = $session->get('_csrf_tokens', []);
        self::assertIsArray($stored);
        self::assertArrayHasKey('form_login', $stored);
        self::assertSame($token, $stored['form_login']);
    }

    public function testIsTokenValidReturnsFalseWhenTokenMissing(): void
    {
        $sessionData = [];
        $session = new Session($sessionData);
        $manager = new CsrfTokenManager($session);

        self::assertFalse($manager->isTokenValid('form_login', null));
    }

    public function testIsTokenValidChecksAgainstStoredToken(): void
    {
        $sessionData = [];
        $session = new Session($sessionData);
        $manager = new CsrfTokenManager($session);

        $token = $manager->generateToken('form_login');

        self::assertTrue($manager->isTokenValid('form_login', $token));
        self::assertFalse($manager->isTokenValid('form_login', 'other-token'));
        self::assertFalse($manager->isTokenValid('other_form', $token));
    }
}
