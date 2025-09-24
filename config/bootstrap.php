<?php

declare(strict_types=1);

use App\Infrastructure\Container\Container;
use App\Infrastructure\Http\Kernel;
use App\Infrastructure\Http\Router;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$parameters = require __DIR__ . '/parameters.php';

$timezone = $parameters['app.timezone'] ?? 'UTC';

try {
    new DateTimeZone($timezone);
} catch (Exception $exception) {
    throw new RuntimeException(sprintf('Invalid application timezone "%s".', $timezone), 0, $exception);
}

date_default_timezone_set($timezone);

$container = new Container($parameters);

$servicesConfigurator = require __DIR__ . '/services.php';
$servicesConfigurator($container);

$router = new Router();
$routesConfigurator = require __DIR__ . '/routes.php';
$routesConfigurator($router);

$container->set(Router::class, fn () => $router);
$container->set(Kernel::class, fn () => new Kernel($router, $container));

return $container;
