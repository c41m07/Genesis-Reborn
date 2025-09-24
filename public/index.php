<?php

declare(strict_types=1);

use App\Infrastructure\Http\Kernel;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Session\SessionInterface;

$container = require __DIR__ . '/../config/bootstrap.php';

/** @var Kernel $kernel */
$kernel = $container->get(Kernel::class);
$session = $container->get(SessionInterface::class);
$request = Request::fromGlobals($session);
$response = $kernel->handle($request);
$response->send();
