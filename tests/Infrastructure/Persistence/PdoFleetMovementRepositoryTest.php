<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Entity\FleetMovement;
use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\ValueObject\Coordinates;
use App\Infrastructure\Persistence\PdoFleetMovementRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoFleetMovementRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoFleetMovementRepository $repository;
    private int $playerId;
    private int $originPlanetId;
    private int $destinationPlanetId;
    private int $shipId;
    private int $garrisonFleetId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema();
        $this->seedBaseData();

        $this->repository = new PdoFleetMovementRepository($this->pdo);
    }

    public function testTransportMissionDeliversAllResourcesAndReturnsWithEmptyHold(): void
    {
        $departure = new DateTimeImmutable('2023-01-01T00:00:00Z');
        $arrival = new DateTimeImmutable('2023-01-01T01:00:00Z');
        $return = new DateTimeImmutable('2023-01-01T02:00:00Z');

        $movement = $this->repository->launchMission(
            $this->playerId,
            $this->originPlanetId,
            null,
            $this->destinationPlanetId,
            Coordinates::fromInts(2, 4, 7),
            FleetMission::Transport,
            FleetStatus::Outbound,
            ['fighter' => 5],
            100,
            $departure,
            $arrival,
            3600,
            [
                'cargo' => [
                    'fuel' => 100,
                    'resources' => ['metal' => 200, 'crystal' => 150],
                ],
            ]
        );

        $this->repository->completeArrival($movement, $arrival);

        $destination = $this->pdo->query('SELECT metal, crystal FROM planets WHERE id = ' . $this->destinationPlanetId)
            ->fetch(PDO::FETCH_ASSOC);

        self::assertSame(['metal' => '200', 'crystal' => '150'], $destination);

        $fleetRow = $this->pdo->query('SELECT status, mission_payload, return_at FROM fleets WHERE id = ' . $movement->getId())
            ->fetch(PDO::FETCH_ASSOC);

        self::assertSame('returning', $fleetRow['status']);
        self::assertNotNull($fleetRow['return_at']);

        $payload = json_decode((string)$fleetRow['mission_payload'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['metal' => 200, 'crystal' => 150], $payload['cargo']['resources']);
        self::assertSame(['metal' => 200, 'crystal' => 150, 'hydrogen' => 0], $payload['cargo']['delivered']);
        self::assertSame(['metal' => 0, 'crystal' => 0, 'hydrogen' => 0], $payload['cargo']['remaining']);
        self::assertTrue($payload['cargo']['delivery_complete']);
        self::assertSame(350, $payload['cargo']['delivered_total']);
        self::assertSame(0, $payload['cargo']['remaining_total']);

        $returns = $this->repository->findReturningMissions($return, $this->playerId);
        self::assertCount(1, $returns);

        $this->repository->completeReturn($returns[0], $return);

        $origin = $this->pdo->query('SELECT metal, crystal, hydrogen FROM planets WHERE id = ' . $this->originPlanetId)
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['metal' => '1000', 'crystal' => '1000', 'hydrogen' => '5000'], $origin);

        $garrisonShips = $this->pdo->query('SELECT quantity FROM fleet_ships WHERE fleet_id = ' . $this->garrisonFleetId . ' AND ship_id = ' . $this->shipId)
            ->fetchColumn();
        self::assertSame('10', $garrisonShips);

        $finalFleet = $this->pdo->query('SELECT status, mission_type FROM fleets WHERE id = ' . $movement->getId())
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['status' => 'completed', 'mission_type' => 'idle'], $finalFleet);

        $finalPayload = json_decode((string)$this->pdo->query('SELECT mission_payload FROM fleets WHERE id = ' . $movement->getId())->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($finalPayload['cargo']['return_complete']);
        self::assertSame(0, $finalPayload['cargo']['returned_total']);
        self::assertSame(0, $finalPayload['cargo']['remaining_total']);
    }

    public function testTransportMissionHandlesPartialDeliveryAndReturnsLeftover(): void
    {
        $this->pdo->exec('UPDATE planets SET metal_capacity = 100, crystal_capacity = 50 WHERE id = ' . $this->destinationPlanetId);

        $departure = new DateTimeImmutable('2023-01-01T00:00:00Z');
        $arrival = new DateTimeImmutable('2023-01-01T01:00:00Z');
        $return = new DateTimeImmutable('2023-01-01T02:00:00Z');

        $movement = $this->repository->launchMission(
            $this->playerId,
            $this->originPlanetId,
            null,
            $this->destinationPlanetId,
            Coordinates::fromInts(2, 4, 7),
            FleetMission::Transport,
            FleetStatus::Outbound,
            ['fighter' => 5],
            120,
            $departure,
            $arrival,
            3600,
            [
                'cargo' => [
                    'fuel' => 120,
                    'resources' => ['metal' => 300, 'crystal' => 150],
                ],
            ]
        );

        $this->repository->completeArrival($movement, $arrival);

        $destination = $this->pdo->query('SELECT metal, crystal FROM planets WHERE id = ' . $this->destinationPlanetId)
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['metal' => '100', 'crystal' => '50'], $destination);

        $fleetRow = $this->pdo->query('SELECT mission_payload FROM fleets WHERE id = ' . $movement->getId())
            ->fetch(PDO::FETCH_ASSOC);
        $payload = json_decode((string)$fleetRow['mission_payload'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['metal' => 200, 'crystal' => 100, 'hydrogen' => 0], $payload['cargo']['remaining']);
        self::assertFalse($payload['cargo']['delivery_complete']);
        self::assertSame(150, $payload['cargo']['delivered_total']);
        self::assertSame(300, $payload['cargo']['remaining_total']);

        $returns = $this->repository->findReturningMissions($return, $this->playerId);
        self::assertCount(1, $returns);

        $this->repository->completeReturn($returns[0], $return);

        $origin = $this->pdo->query('SELECT metal, crystal FROM planets WHERE id = ' . $this->originPlanetId)
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['metal' => '1200', 'crystal' => '1100'], $origin);

        $fleetRow = $this->pdo->query('SELECT mission_payload FROM fleets WHERE id = ' . $movement->getId())
            ->fetch(PDO::FETCH_ASSOC);
        $finalPayload = json_decode((string)$fleetRow['mission_payload'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['metal' => 200, 'crystal' => 100, 'hydrogen' => 0], $finalPayload['cargo']['returned']);
        self::assertSame(['metal' => 0, 'crystal' => 0, 'hydrogen' => 0], $finalPayload['cargo']['remaining']);
        self::assertTrue($finalPayload['cargo']['return_complete']);
        self::assertSame(300, $finalPayload['cargo']['returned_total']);
        self::assertSame(0, $finalPayload['cargo']['remaining_total']);
    }

    public function testFindActiveByPlayerReturnsOutboundAndReturningMissions(): void
    {
        $departure = new DateTimeImmutable('2023-01-01T00:00:00Z');
        $arrival = new DateTimeImmutable('2023-01-01T01:00:00Z');

        $this->repository->launchMission(
            $this->playerId,
            $this->originPlanetId,
            null,
            $this->destinationPlanetId,
            Coordinates::fromInts(2, 4, 7),
            FleetMission::Transport,
            FleetStatus::Outbound,
            ['fighter' => 5],
            50,
            $departure,
            $arrival,
            3600,
            [
                'cargo' => [
                    'fuel' => 50,
                    'resources' => ['metal' => 0, 'crystal' => 0],
                ],
            ]
        );

        $returning = $this->repository->launchMission(
            $this->playerId,
            $this->originPlanetId,
            null,
            $this->destinationPlanetId,
            Coordinates::fromInts(2, 4, 8),
            FleetMission::Transport,
            FleetStatus::Outbound,
            ['fighter' => 3],
            40,
            $departure,
            $arrival,
            3600,
            [
                'cargo' => [
                    'fuel' => 40,
                    'resources' => ['metal' => 0, 'crystal' => 0],
                ],
            ]
        );

        $this->repository->completeArrival($returning, $arrival);

        $active = $this->repository->findActiveByPlayer($this->playerId);
        self::assertCount(2, $active);
        $statuses = array_map(static fn (FleetMovement $movement): string => $movement->getStatus()->value, $active);
        sort($statuses);
        self::assertSame(['outbound', 'returning'], $statuses);
    }

    private function createSchema(): void
    {
        $this->pdo->exec('CREATE TABLE players (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, username TEXT, password_hash TEXT)');
        $this->pdo->exec('CREATE TABLE planets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER NOT NULL,
            name TEXT,
            galaxy INTEGER,
            system INTEGER,
            position INTEGER,
            metal INTEGER,
            crystal INTEGER,
            hydrogen INTEGER,
            energy INTEGER,
            metal_capacity INTEGER,
            crystal_capacity INTEGER,
            hydrogen_capacity INTEGER,
            energy_capacity INTEGER,
            last_resource_tick TEXT,
            created_at TEXT,
            updated_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE ships (id INTEGER PRIMARY KEY AUTOINCREMENT, `key` TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE fleets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER NOT NULL,
            origin_planet_id INTEGER NOT NULL,
            destination_planet_id INTEGER NULL,
            mission_type TEXT,
            status TEXT,
            mission_payload TEXT,
            departure_at TEXT,
            arrival_at TEXT,
            return_at TEXT,
            travel_time_seconds INTEGER,
            fuel_consumed INTEGER,
            created_at TEXT,
            updated_at TEXT
        )');
        $this->pdo->exec('CREATE TABLE fleet_ships (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER,
            fleet_id INTEGER,
            ship_id INTEGER,
            quantity INTEGER,
            created_at TEXT,
            updated_at TEXT
        )');
    }

    private function seedBaseData(): void
    {
        $now = '2023-01-01 00:00:00';

        $this->pdo->exec("INSERT INTO players (email, username, password_hash) VALUES ('player@example.com', 'player', 'hash')");
        $this->playerId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec(sprintf(
            "INSERT INTO planets (player_id, name, galaxy, system, position, metal, crystal, hydrogen, energy, metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity, last_resource_tick, created_at, updated_at) VALUES (%d, 'Origin', 1, 1, 1, 1000, 1000, 5000, 0, 10000, 10000, 10000, 0, '%s', '%s', '%s')",
            $this->playerId,
            $now,
            $now,
            $now
        ));
        $this->originPlanetId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec(sprintf(
            "INSERT INTO planets (player_id, name, galaxy, system, position, metal, crystal, hydrogen, energy, metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity, last_resource_tick, created_at, updated_at) VALUES (%d, 'Destination', 2, 4, 7, 0, 0, 0, 0, 10000, 10000, 10000, 0, '%s', '%s', '%s')",
            $this->playerId,
            $now,
            $now,
            $now
        ));
        $this->destinationPlanetId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec("INSERT INTO ships (`key`) VALUES ('fighter')");
        $this->shipId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec(sprintf(
            "INSERT INTO fleets (player_id, origin_planet_id, destination_planet_id, mission_type, status, mission_payload, departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at) VALUES (%d, %d, NULL, 'idle', 'idle', NULL, NULL, NULL, NULL, 0, 0, '%s', '%s')",
            $this->playerId,
            $this->originPlanetId,
            $now,
            $now
        ));
        $this->garrisonFleetId = (int)$this->pdo->lastInsertId();

        $this->pdo->exec(sprintf(
            "INSERT INTO fleet_ships (player_id, fleet_id, ship_id, quantity, created_at, updated_at) VALUES (%d, %d, %d, 10, '%s', '%s')",
            $this->playerId,
            $this->garrisonFleetId,
            $this->shipId,
            $now,
            $now
        ));
    }
}
