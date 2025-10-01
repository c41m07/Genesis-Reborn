<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Http\Session\Session;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    private function createRequest(string $method, string $path): Request
    {
        $sessionData = [];
        $session = new Session($sessionData);

        return new Request(strtoupper($method), $path, [], [], $session);
    }

    public function testMatchReturnsRouteForStaticPath(): void
    {
        $this->router->add('GET', '/dashboard', [ControllerStub::class, 'index']);

        $route = $this->router->match($this->createRequest('GET', '/dashboard'));

        self::assertNotNull($route);
        self::assertSame('GET', $route->getMethod());
        self::assertSame('/dashboard', $route->getPath());
        self::assertSame([ControllerStub::class, 'index'], $route->getHandler());
        self::assertSame([], $route->getParameters());
    }

    public function testMatchExtractsParametersFromDynamicRoute(): void
    {
        $this->router->add('GET', '/profile/{username}', [ControllerStub::class, 'show']);

        $route = $this->router->match($this->createRequest('GET', '/profile/jane-doe'));

        self::assertNotNull($route);
        self::assertSame(['username' => 'jane-doe'], $route->getParameters());
    }

    public function testMatchNormalisesTrailingSlash(): void
    {
        $this->router->add('POST', '/fleet/{id}', [ControllerStub::class, 'update']);

        $route = $this->router->match($this->createRequest('POST', '/fleet/42/'));

        self::assertNotNull($route);
        self::assertSame(['id' => '42'], $route->getParameters());
    }

    public function testMatchReturnsNullWhenMethodDoesNotMatch(): void
    {
        $this->router->add('POST', '/login', [ControllerStub::class, 'login']);

        $route = $this->router->match($this->createRequest('GET', '/login'));

        self::assertNull($route);
    }
}

final class ControllerStub
{
    public function __invoke(): void
    {
    }
}
