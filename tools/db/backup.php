<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$parameters = require $root . '/config/parameters.php';

$host = (string)($parameters['db.host'] ?? '127.0.0.1');
$port = (int)($parameters['db.port'] ?? 3306);
$dbName = (string)($parameters['db.name'] ?? 'genesis_reborn');
$user = (string)($parameters['db.user'] ?? 'root');
$password = (string)($parameters['db.pass'] ?? '');

$mysqldump = trim((string) shell_exec('command -v mysqldump')); // @phpstan-ignore-line
if ($mysqldump === '') {
    fwrite(STDERR, "mysqldump introuvable. Veuillez installer le client MySQL ou réaliser un dump manuel.\n");
    exit(1);
}

$backupDir = $root . '/var/backups/db';
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    throw new \RuntimeException(sprintf('Impossible de créer le répertoire de sauvegarde %s', $backupDir));
}

$timestamp = (new \DateTimeImmutable('now'))->format('Ymd_His');
$file = sprintf('%s/%s_backup.sql', $backupDir, $timestamp);

$command = sprintf(
    '%s --single-transaction --skip-lock-tables --host=%s --port=%d --user=%s %s > %s',
    escapeshellcmd($mysqldump),
    escapeshellarg($host),
    $port,
    escapeshellarg($user),
    escapeshellarg($dbName),
    escapeshellarg($file)
);

if ($password !== '') {
    $command = sprintf('MYSQL_PWD=%s %s', escapeshellarg($password), $command);
}

passthru($command, $exitCode);

if ($exitCode !== 0) {
    @unlink($file);
    throw new \RuntimeException(sprintf('Échec du dump MySQL (code %d).', $exitCode));
}

echo sprintf("Backup enregistré : %s\n", $file);
