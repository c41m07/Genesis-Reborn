<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

$envPath = dirname(__DIR__, 2);

if (is_file($envPath . '/.env')) {
    Dotenv::createImmutable($envPath)->safeLoad();
}

$parameters = require $envPath . '/config/parameters.php';

$host = (string)($parameters['db.host'] ?? '127.0.0.1');
$port = (int)($parameters['db.port'] ?? 3306);
$dbName = (string)($parameters['db.name'] ?? 'genesis_reborn');
$user = (string)($parameters['db.user'] ?? 'root');
$password = $parameters['db.pass'] ?? null;

$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
$options = [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
];

// On veut pouvoir exécuter plusieurs statements d'un coup (fichiers .sql)
if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
    $options[\PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
}

$pdo = new \PDO($dsn, $user, $password, $options);

// 1) Crée la base si besoin puis sélectionne-la
$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    str_replace('`', '``', $dbName)
));
$pdo->exec(sprintf('USE `%s`', str_replace('`', '``', $dbName)));

echo sprintf("Database '%s' selected.%s", $dbName, PHP_EOL);

// 2) Bootstrap: s’assurer que la table migrations existe AVANT de la lire
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        checksum VARCHAR(64) NULL COMMENT \'SHA256 hash of file content for integrity\',
        INDEX idx_migrations_filename (filename),
        INDEX idx_migrations_applied (applied_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

// 3) Récupérer la liste des migrations déjà appliquées
$appliedMigrations = [];
$statement = $pdo->prepare('SELECT filename, checksum FROM migrations');
$statement->execute();
foreach ($statement->fetchAll() as $row) {
    $appliedMigrations[$row['filename']] = $row['checksum'];
}

// 4) Récupérer tous les fichiers .sql
function parseMigration(string $sql): array
{
    $sections = [
        'up' => [],
        'down' => [],
    ];

    $current = null;
    foreach (preg_split("/(\r\n|\r|\n)/", $sql) as $line) {
        if (preg_match('/^--\s*migrate:(up|down)\s*$/i', trim($line), $matches)) {
            $current = strtolower($matches[1]);
            continue;
        }

        if ($current !== null) {
            $sections[$current][] = $line;
        }
    }

    if (empty($sections['up'])) {
        $sections['up'] = [$sql];
    }

    return [
        'up' => trim(implode(PHP_EOL, $sections['up'])),
        'down' => trim(implode(PHP_EOL, $sections['down'])),
    ];
}

$command = $argv[1] ?? 'up';

$migrationGlobs = [
    $envPath . '/database/migrations/*.sql',
];

$files = [];
foreach ($migrationGlobs as $pattern) {
    $matches = glob($pattern);
    if ($matches !== false) {
        $files = array_merge($files, $matches);
    }
}

if ($command === '--down') {
    $target = $argv[2] ?? 'last';
    $fileToRollback = null;
    $filename = null;

    if ($target === 'last') {
        $stmt = $pdo->query('SELECT filename FROM migrations ORDER BY applied_at DESC LIMIT 1');
        $row = $stmt->fetch();
        if ($row === false) {
            echo "No migrations have been applied yet." . PHP_EOL;
            exit(0);
        }
        $filename = $row['filename'];
    } else {
        $filename = basename($target);
    }

    foreach ($files as $file) {
        if (basename($file) === $filename) {
            $fileToRollback = $file;
            break;
        }
    }

    if ($fileToRollback === null) {
        throw new \RuntimeException(sprintf('Cannot find migration file to rollback: %s', $filename));
    }

    $sections = parseMigration((string) file_get_contents($fileToRollback));
    if ($sections['down'] === '') {
        throw new \RuntimeException(sprintf('Migration %s does not provide a -- migrate:down section.', $filename));
    }

    echo sprintf('Rolling back %s...%s', $filename, PHP_EOL);
    $pdo->exec($sections['down']);
    $pdo->prepare('DELETE FROM migrations WHERE filename = ?')->execute([$filename]);
    echo 'Rollback completed.' . PHP_EOL;
    exit(0);
}

if ($files === []) {
    throw new \RuntimeException('Unable to read migrations directories.');
}

sort($files);

$newMigrationsApplied = 0;

foreach ($files as $file) {
    $now = (new \DateTimeImmutable())->format('H:i:s');
    $filename = basename($file);
    echo sprintf('[%s] Processing %s...%s', $now, $filename, PHP_EOL);

    $rawSql = file_get_contents($file);
    if ($rawSql === false) {
        throw new \RuntimeException(sprintf('Cannot read migration file: %s', $file));
    }

    $sections = parseMigration($rawSql);
    $sql = $sections['up'];
    if ($sql === '') {
        echo sprintf('[%s] %s has no statements to execute.%s',
            (new \DateTimeImmutable())->format('H:i:s'), $filename, PHP_EOL);
        continue;
    }

    $checksum = hash('sha256', $rawSql);

    if (isset($appliedMigrations[$filename])) {
        if ($appliedMigrations[$filename] !== $checksum && $appliedMigrations[$filename] !== 'SKIPPED_DATA_EXISTS') {
            echo sprintf('[%s] WARNING: %s has changed since last application!%s',
                (new \DateTimeImmutable())->format('H:i:s'), $filename, PHP_EOL);
        }
        echo sprintf('[%s] %s already applied.%s',
            (new \DateTimeImmutable())->format('H:i:s'), $filename, PHP_EOL);
        continue;
    }

    try {
        $pdo->exec($sql);

        $pdo->prepare('INSERT INTO migrations (filename, checksum) VALUES (?, ?)')
            ->execute([$filename, $checksum]);

        echo sprintf('[%s] %s applied.%s',
            (new \DateTimeImmutable())->format('H:i:s'), $filename, PHP_EOL);
        $newMigrationsApplied++;

    } catch (\Throwable $e) {
        throw new \RuntimeException(sprintf(
            'Failed to apply migration %s: %s',
            $filename,
            $e->getMessage()
        ));
    }
}

if ($newMigrationsApplied === 0) {
    echo 'All migrations are up to date.' . PHP_EOL;
} else {
    echo sprintf('Applied %d new migration(s). Database is ready.%s', $newMigrationsApplied, PHP_EOL);
}
