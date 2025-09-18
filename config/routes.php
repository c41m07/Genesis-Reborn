<?php

use App\Controller\AuthController;
use App\Controller\ColonyController;
use App\Controller\DashboardController;
use App\Controller\FleetController;
use App\Controller\JournalController;
use App\Controller\ProfileController;
use App\Controller\ResearchController;
use App\Controller\ShipyardController;
use App\Controller\TechTreeController;
use App\Infrastructure\Http\Router;

return function (Router $router): void {
    $router->add('GET', '/', [AuthController::class, 'login']);
    $router->add('GET', '/login', [AuthController::class, 'login']);
    $router->add('POST', '/login', [AuthController::class, 'login']);
    $router->add('GET', '/register', [AuthController::class, 'register']);
    $router->add('POST', '/register', [AuthController::class, 'register']);
    $router->add('POST', '/logout', [AuthController::class, 'logout']);

    $router->add('GET', '/dashboard', [DashboardController::class, 'index']);

    $router->add('GET', '/colony', [ColonyController::class, 'index']);
    $router->add('POST', '/colony', [ColonyController::class, 'index']);

    $router->add('GET', '/research', [ResearchController::class, 'index']);
    $router->add('POST', '/research', [ResearchController::class, 'index']);

    $router->add('GET', '/shipyard', [ShipyardController::class, 'index']);
    $router->add('POST', '/shipyard', [ShipyardController::class, 'index']);

    $router->add('GET', '/fleet', [FleetController::class, 'index']);
    $router->add('POST', '/fleet', [FleetController::class, 'index']);

    $router->add('GET', '/journal', [JournalController::class, 'index']);

    $router->add('GET', '/profile', [ProfileController::class, 'index']);

    $router->add('GET', '/tech-tree', [TechTreeController::class, 'index']);
};
