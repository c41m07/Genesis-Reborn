<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

class ConnectionFactory
{
    public function __construct(
        private readonly string  $host,
        private readonly int     $port,
        private readonly string  $dbName,
        private readonly string  $user,
        private readonly ?string $password
    ) {
    }

    public function create(): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->dbName);

        $pdo = new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $timezoneId = date_default_timezone_get();

            try {
                $this->applyTimezone($pdo, $timezoneId);
            } catch (PDOException $exception) {
                $offset = $this->formatOffset($timezoneId);

                if ($offset === null) {
                    throw $exception;
                }

                $this->applyTimezone($pdo, $offset);
            }
        }

        return $pdo;
    }

    private function applyTimezone(PDO $pdo, string $timezone): void
    {
        $statement = $pdo->prepare('SET time_zone = :timezone');
        $statement->execute(['timezone' => $timezone]);
    }

    private function formatOffset(string $timezoneId): ?string
    {
        try {
            $timezone = new DateTimeZone($timezoneId);
        } catch (\Exception) {
            return null;
        }

        $now = new DateTimeImmutable('now', $timezone);
        $offsetInSeconds = $timezone->getOffset($now);
        $sign = $offsetInSeconds >= 0 ? '+' : '-';
        $absoluteOffset = abs($offsetInSeconds);
        $hours = intdiv($absoluteOffset, 3600);
        $minutes = intdiv($absoluteOffset % 3600, 60);

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }
}
