<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

$envPath = dirname(__DIR__, 2);

if (is_file($envPath . '/.env')) {
    Dotenv::createImmutable($envPath)->safeLoad();
}

$parameters = require $envPath . '/config/parameters.php';

$host = (string) ($parameters['db.host'] ?? '127.0.0.1');
$port = (int) ($parameters['db.port'] ?? 3306);
$dbName = (string) ($parameters['db.name'] ?? 'genesis_reborn');
$user = (string) ($parameters['db.user'] ?? 'root');
$password = $parameters['db.pass'] ?? null;

$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
}

$pdo = new PDO($dsn, $user, $password, $options);

$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    str_replace('`', '``', $dbName)
));
$pdo->exec(sprintf('USE `%s`', str_replace('`', '``', $dbName)));

echo sprintf("Database '%s' selected.%s", $dbName, PHP_EOL);

$migrationDir = $envPath . '/migrations';
$files = glob($migrationDir . '/*.sql');
if ($files === false) {
    throw new RuntimeException('Unable to read migrations directory.');
}

sort($files);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException(sprintf('Cannot read migration file: %s', $file));
    }

    $pdo->exec($sql);
    echo sprintf('[%s] %s applied.%s', (new DateTimeImmutable())->format('H:i:s'), basename($file), PHP_EOL);
}

echo 'Database is ready.' . PHP_EOL;
