<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

class Router
{
    /**
     * @var array<int, array{method: string, path: string, regex: string, handler: array{0: string, 1: string}}>
     */
    private array $routes = [];

    /**
     * @param array{0: string, 1: string} $handler
     */
    public function add(string $method, string $path, array $handler): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        if ($pattern === null) {
            throw new RuntimeException('Unable to compile route pattern for ' . $path);
        }
        $regex = '#^' . $pattern . '$#';
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    public function match(Request $request): ?Route
    {
        $method = $request->getMethod();
        $path = rtrim($request->getPath(), '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return new Route($route['method'], $route['path'], $route['handler'], $params);
        }

        return null;
    }
}
