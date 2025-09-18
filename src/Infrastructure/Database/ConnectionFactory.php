<?php

namespace App\Infrastructure\Database;

use PDO;

class ConnectionFactory
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $dbName,
        private readonly string $user,
        private readonly ?string $password
    ) {
    }

    public function create(): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->dbName);

        return new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
