<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\Http\Session\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function testSetGetAndRemove(): void
    {
        $storage = [];
        $session = new Session($storage);

        $session->set('user_id', 42);
        self::assertTrue($session->has('user_id'));
        self::assertSame(42, $session->get('user_id'));
        self::assertSame(42, $storage['user_id']);

        $session->remove('user_id');
        self::assertFalse($session->has('user_id'));
        self::assertSame([], $storage);
    }

    public function testFlashAndPull(): void
    {
        $storage = [];
        $session = new Session($storage);

        $session->flash('success', 'ok');
        $session->flash('success', 'again');

        $flashes = $session->pull('_flashes', []);
        self::assertSame(['success' => ['ok', 'again']], $flashes);
        self::assertFalse($session->has('_flashes'));
        self::assertSame([], $storage);
    }

    public function testPullReturnsDefaultWhenMissing(): void
    {
        $storage = [];
        $session = new Session($storage);

        self::assertSame('default', $session->pull('unknown', 'default'));
    }
}
