<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\FleetMovement;
use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\ValueObject\Coordinates;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class PdoFleetMovementRepository implements FleetMovementRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function launchMission(
        int $playerId,
        int $originPlanetId,
        ?int $destinationPlanetId,
        Coordinates $destinationCoordinates,
        FleetMission $mission,
        FleetStatus $status,
        array $composition,
        int $fuelConsumed,
        DateTimeImmutable $departureAt,
        DateTimeImmutable $arrivalAt,
        int $travelTimeSeconds
    ): FleetMovement {
        $this->pdo->beginTransaction();

        try {
            $garrisonId = $this->ensureGarrisonFleet($playerId, $originPlanetId);
            $missionId = $this->insertMission(
                $playerId,
                $originPlanetId,
                $destinationPlanetId,
                $destinationCoordinates,
                $mission,
                $status,
                $fuelConsumed,
                $departureAt,
                $arrivalAt,
                $travelTimeSeconds,
                $composition
            );

            foreach ($composition as $shipKey => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $shipId = $this->getShipIdByKey($shipKey);
                if ($shipId === null) {
                    throw new RuntimeException(sprintf('Type de vaisseau "%s" inconnu.', $shipKey));
                }

                $this->deductGarrisonShip($garrisonId, $shipId, $quantity);
                $this->assignShipToMission($missionId, $playerId, $shipId, $quantity);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->getMovement($missionId);
    }

    public function findActiveByOriginPlanet(int $planetId): array
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT f.*, 
                   po.galaxy AS origin_galaxy,
                   po.system AS origin_system,
                   po.position AS origin_position,
                   pd.galaxy AS destination_galaxy,
                   pd.system AS destination_system,
                   pd.position AS destination_position
            FROM fleets f
            INNER JOIN planets po ON po.id = f.origin_planet_id
            LEFT JOIN planets pd ON pd.id = f.destination_planet_id
            WHERE f.origin_planet_id = :planet
              AND f.status IN ('outbound','returning','holding')
            ORDER BY f.arrival_at ASC
        SQL);
        $stmt->execute(['planet' => $planetId]);

        $movements = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $movements[] = $this->hydrateMovement($row);
        }

        return $movements;
    }

    public function findArrivedMissions(DateTimeImmutable $now, ?int $playerId = null): array
    {
        $query = <<<SQL
            SELECT f.*, 
                   po.galaxy AS origin_galaxy,
                   po.system AS origin_system,
                   po.position AS origin_position,
                   pd.galaxy AS destination_galaxy,
                   pd.system AS destination_system,
                   pd.position AS destination_position
            FROM fleets f
            INNER JOIN planets po ON po.id = f.origin_planet_id
            LEFT JOIN planets pd ON pd.id = f.destination_planet_id
            WHERE f.status = 'outbound'
              AND f.arrival_at IS NOT NULL
              AND f.arrival_at <= :now
        SQL;

        $params = ['now' => $now->format('Y-m-d H:i:s')];
        if ($playerId !== null) {
            $query .= ' AND f.player_id = :player';
            $params['player'] = $playerId;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        $movements = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $movements[] = $this->hydrateMovement($row);
        }

        return $movements;
    }

    public function completeArrival(FleetMovement $movement, DateTimeImmutable $processedAt): void
    {
        $this->pdo->beginTransaction();

        try {
            $garrisonId = $this->ensureGarrisonFleet($movement->getPlayerId(), $movement->getOriginPlanetId());
            $composition = $movement->getComposition();

            foreach ($composition as $shipKey => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $shipId = $this->getShipIdByKey($shipKey);
                if ($shipId === null) {
                    continue;
                }

                $this->assignShipToMission($garrisonId, $movement->getPlayerId(), $shipId, $quantity);
                $this->removeShipFromMission($movement->getId(), $shipId);
            }

            $payload = json_encode([
                'completed_at' => $processedAt->format(DATE_ATOM),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $update = $this->pdo->prepare('UPDATE fleets SET status = :status, mission_type = :mission, destination_planet_id = NULL, mission_payload = :payload, return_at = :processedAt WHERE id = :id');
            $update->execute([
                'status' => FleetStatus::Completed->value,
                'mission' => FleetMission::Idle->value,
                'payload' => $payload,
                'processedAt' => $processedAt->format('Y-m-d H:i:s'),
                'id' => $movement->getId(),
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, int> $composition
     */
    private function insertMission(
        int $playerId,
        int $originPlanetId,
        ?int $destinationPlanetId,
        Coordinates $destinationCoordinates,
        FleetMission $mission,
        FleetStatus $status,
        int $fuelConsumed,
        DateTimeImmutable $departureAt,
        DateTimeImmutable $arrivalAt,
        int $travelTimeSeconds,
        array $composition
    ): int {
        $payload = json_encode([
            'destination' => $destinationCoordinates->toArray(),
            'composition' => $composition,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare('INSERT INTO fleets (player_id, origin_planet_id, destination_planet_id, mission_type, status, mission_payload, departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at) VALUES (:player, :origin, :destination, :mission, :status, :payload, :departure, :arrival, NULL, :travel, :fuel, NOW(), NOW())');
        $stmt->execute([
            'player' => $playerId,
            'origin' => $originPlanetId,
            'destination' => $destinationPlanetId,
            'mission' => $mission->value,
            'status' => $status->value,
            'payload' => $payload,
            'departure' => $departureAt->format('Y-m-d H:i:s'),
            'arrival' => $arrivalAt->format('Y-m-d H:i:s'),
            'travel' => $travelTimeSeconds,
            'fuel' => $fuelConsumed,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function ensureGarrisonFleet(int $playerId, int $planetId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM fleets WHERE player_id = :player AND origin_planet_id = :planet AND mission_type = :mission AND status = :status AND destination_planet_id IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'mission' => FleetMission::Idle->value,
            'status' => FleetStatus::Idle->value,
        ]);
        $fleetId = $stmt->fetchColumn();

        if ($fleetId !== false) {
            return (int)$fleetId;
        }

        $create = $this->pdo->prepare('INSERT INTO fleets (player_id, origin_planet_id, destination_planet_id, mission_type, status, mission_payload, departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at) VALUES (:player, :planet, NULL, :mission, :status, NULL, NULL, NULL, NULL, 0, 0, NOW(), NOW())');
        $create->execute([
            'player' => $playerId,
            'planet' => $planetId,
            'mission' => FleetMission::Idle->value,
            'status' => FleetStatus::Idle->value,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function getShipIdByKey(string $key): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM ships WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        $shipId = $stmt->fetchColumn();

        if ($shipId === false) {
            return null;
        }

        return (int)$shipId;
    }

    private function deductGarrisonShip(int $fleetId, int $shipId, int $quantity): void
    {
        $stmt = $this->pdo->prepare('UPDATE fleet_ships SET quantity = quantity - :quantity WHERE fleet_id = :fleet AND ship_id = :ship');
        $stmt->execute([
            'quantity' => $quantity,
            'fleet' => $fleetId,
            'ship' => $shipId,
        ]);

        $cleanup = $this->pdo->prepare('DELETE FROM fleet_ships WHERE fleet_id = :fleet AND ship_id = :ship AND quantity <= 0');
        $cleanup->execute([
            'fleet' => $fleetId,
            'ship' => $shipId,
        ]);
    }

    private function assignShipToMission(int $fleetId, int $playerId, int $shipId, int $quantity): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO fleet_ships (player_id, fleet_id, ship_id, quantity, created_at, updated_at) VALUES (:player, :fleet, :ship, :quantity, NOW(), NOW()) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'player' => $playerId,
            'fleet' => $fleetId,
            'ship' => $shipId,
            'quantity' => $quantity,
        ]);
    }

    private function removeShipFromMission(int $fleetId, int $shipId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM fleet_ships WHERE fleet_id = :fleet AND ship_id = :ship');
        $stmt->execute([
            'fleet' => $fleetId,
            'ship' => $shipId,
        ]);
    }

    private function getMovement(int $missionId): FleetMovement
    {
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT f.*, 
                   po.galaxy AS origin_galaxy,
                   po.system AS origin_system,
                   po.position AS origin_position,
                   pd.galaxy AS destination_galaxy,
                   pd.system AS destination_system,
                   pd.position AS destination_position
            FROM fleets f
            INNER JOIN planets po ON po.id = f.origin_planet_id
            LEFT JOIN planets pd ON pd.id = f.destination_planet_id
            WHERE f.id = :id
        SQL);
        $stmt->execute(['id' => $missionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('Mission de flotte introuvable.');
        }

        return $this->hydrateMovement($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateMovement(array $row): FleetMovement
    {
        $payload = [];
        if (!empty($row['mission_payload'])) {
            $decoded = json_decode((string)$row['mission_payload'], true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $composition = $this->getComposition((int)$row['id']);
        $destinationCoordinates = $payload['destination'] ?? [
            'galaxy' => (int)($row['destination_galaxy'] ?? $row['origin_galaxy']),
            'system' => (int)($row['destination_system'] ?? $row['origin_system']),
            'position' => (int)($row['destination_position'] ?? $row['origin_position']),
        ];

        return new FleetMovement(
            (int)$row['id'],
            (int)$row['player_id'],
            (int)$row['origin_planet_id'],
            isset($row['destination_planet_id']) ? (int)$row['destination_planet_id'] : null,
            Coordinates::fromInts((int)$row['origin_galaxy'], (int)$row['origin_system'], (int)$row['origin_position']),
            Coordinates::fromArray($destinationCoordinates),
            FleetMission::fromString((string)$row['mission_type']),
            FleetStatus::fromString((string)$row['status']),
            $composition,
            $this->parseDateTime($row['departure_at']),
            $this->parseNullableDateTime($row['arrival_at']),
            $this->parseNullableDateTime($row['return_at']),
            (int)$row['travel_time_seconds'],
            (int)$row['fuel_consumed'],
            $payload
        );
    }

    /**
     * @return array<string, int>
     */
    private function getComposition(int $fleetId): array
    {
        $stmt = $this->pdo->prepare('SELECT s.`key`, fs.quantity FROM fleet_ships fs JOIN ships s ON s.id = fs.ship_id WHERE fs.fleet_id = :fleet');
        $stmt->execute(['fleet' => $fleetId]);
        $composition = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $composition[$row['key']] = (int)$row['quantity'];
        }

        return $composition;
    }

    private function parseDateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string)$value);
    }

    private function parseNullableDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        $value = (string)$value;
        if ($value === '') {
            return null;
        }

        return new DateTimeImmutable($value);
    }
}
