<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Persistence\PdoHangarRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PdoHangarRepositoryTest extends TestCase
{
    public function testAddAndRemoveShipsUpdatesStock(): void
    {
        $pdo = $this->createConnection();
        $repository = new PdoHangarRepository($pdo);

        $repository->addShips(1, 'fighter', 3);
        $repository->addShips(1, 'fighter', 2);
        $repository->addShips(1, 'bomber', 1);

        self::assertEqualsCanonicalizing(['fighter' => 5, 'bomber' => 1], $repository->getStock(1));
        self::assertSame(5, $repository->getQuantity(1, 'fighter'));

        $repository->removeShips(1, 'fighter', 4);
        self::assertEqualsCanonicalizing(['fighter' => 1, 'bomber' => 1], $repository->getStock(1));

        $repository->removeShips(1, 'fighter', 1);
        self::assertSame(['bomber' => 1], $repository->getStock(1));
    }

    public function testRemoveShipsThrowsWhenInsufficient(): void
    {
        $pdo = $this->createConnection();
        $repository = new PdoHangarRepository($pdo);

        $repository->addShips(1, 'fighter', 1);

        $this->expectException(RuntimeException::class);
        $repository->removeShips(1, 'fighter', 2);
    }

    private function createConnection(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE players (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT)');
        $pdo->exec('CREATE TABLE planets (id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE ships (id INTEGER PRIMARY KEY AUTOINCREMENT, `key` TEXT NOT NULL UNIQUE)');
        $pdo->exec('CREATE TABLE planet_hangar_ships (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER NOT NULL,
            planet_id INTEGER NOT NULL,
            ship_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec("INSERT INTO players (id, username) VALUES (1, 'Commander')");
        $pdo->exec('INSERT INTO planets (id, player_id) VALUES (1, 1)');
        $pdo->exec("INSERT INTO ships (id, `key`) VALUES (1, 'fighter'), (2, 'bomber')");

        return $pdo;
    }
}
