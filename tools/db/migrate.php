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
$migrationDir = $envPath . '/migrations';
$files = glob($migrationDir . '/*.sql');
if ($files === false) {
    throw new \RuntimeException('Unable to read migrations directory.');
}

// Tri lexical (assure l’ordre si tes fichiers sont préfixés par timestamps)
sort($files);

$newMigrationsApplied = 0;

foreach ($files as $file) {
    $now = (new \DateTimeImmutable())->format('H:i:s');
    $filename = basename($file);
    echo sprintf('[%s] Processing %s...%s', $now, $filename, PHP_EOL);

//    // 4.a) Skip spécial pour une migration destructrice si des données existent
//    if ($filename === '20250920_schema.sql') {
//        $hasData = false;
//        try {
//            $checkStmt = $pdo->query('SELECT COUNT(*) AS count FROM players');
//            $hasData = ((int) $checkStmt->fetch()['count']) > 0;
//        } catch (\Throwable $e) {
//            // Table players absente => pas de données, on pourra appliquer
//        }
//
//        if ($hasData) {
//            echo sprintf('[%s] %s SKIPPED (destructive, data exists).%s',
//                (new \DateTimeImmutable())->format('H:i:s'), $filename, PHP_EOL);
//
//            if (!isset($appliedMigrations[$filename])) {
//                $pdo->prepare('INSERT IGNORE INTO migrations (filename, checksum) VALUES (?, ?)')
//                    ->execute([$filename, 'SKIPPED_DATA_EXISTS']);
//                $appliedMigrations[$filename] = 'SKIPPED_DATA_EXISTS';
//            }
//            continue;
//        }
//    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new \RuntimeException(sprintf('Cannot read migration file: %s', $file));
    }

    $checksum = hash('sha256', $sql);

    // 4.b) Déjà appliquée ?
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
        // ❌ PAS de transaction autour d’un gros fichier DDL
        // $pdo->beginTransaction();

        // Exécuter le fichier SQL (multi-statements OK)
        $pdo->exec($sql);

        // Marquer comme appliqué
        $pdo->prepare('INSERT INTO migrations (filename, checksum) VALUES (?, ?)')
            ->execute([$filename, $checksum]);

        // ❌ Pas de commit non plus
        // if ($pdo->inTransaction()) { $pdo->commit(); }

        echo sprintf('[%s] %s applied.%s',
            (new \DateTimeImmutable())->format('H:i:s'), $filename, PHP_EOL);
        $newMigrationsApplied++;

    } catch (\Throwable $e) {
        // ⚠️ Ne JAMAIS rollback ici: DDL = commits implicites
        // if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (\Throwable $ignored) {} }

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
