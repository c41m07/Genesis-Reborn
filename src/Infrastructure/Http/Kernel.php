<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Container\Container;
use RuntimeException;
use Throwable;

class Kernel
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container
    ) {
    }

    public function handle(Request $request): Response
    {
        $route = $this->router->match($request);
        if (!$route) {
            return new Response('Not Found', 404);
        }

        foreach ($route->getParameters() as $key => $value) {
            $request->setAttribute($key, $value);
        }

        [$class, $method] = $route->getHandler();
        $controller = $this->container->get($class);

        try {
            $response = $controller->$method($request);
        } catch (Throwable $exception) {
            if ($this->container->getParameter('app.debug', false)) {
                throw $exception;
            }

            return new Response('Internal Server Error', 500);
        }

        if (!$response instanceof Response) {
            throw new RuntimeException(sprintf('Controller %s::%s must return a Response instance.', $class, $method));
        }

        return $response;
    }
}
