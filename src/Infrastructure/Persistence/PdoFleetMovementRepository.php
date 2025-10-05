<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\FleetMovement;
use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\ValueObject\Coordinates;
use DateInterval;
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
        ?int $fleetId,
        ?int $destinationPlanetId,
        Coordinates $destinationCoordinates,
        FleetMission $mission,
        FleetStatus $status,
        array $composition,
        int $fuelConsumed,
        DateTimeImmutable $departureAt,
        DateTimeImmutable $arrivalAt,
        int $travelTimeSeconds,
        array $payload = []
    ): FleetMovement {
        $this->pdo->beginTransaction();

        try {
            if ($fleetId !== null) {
                $missionId = $this->activateFleetMission(
                    $fleetId,
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
                    $composition,
                    $payload
                );
            } else {
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
                    $composition,
                    $payload
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

    public function findActiveByPlayer(int $playerId): array
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
            WHERE f.player_id = :player
              AND f.status IN ('outbound','returning')
            ORDER BY CASE WHEN f.status = 'returning' THEN f.return_at ELSE f.arrival_at END ASC
        SQL);
        $stmt->execute(['player' => $playerId]);

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

    public function findReturningMissions(DateTimeImmutable $now, ?int $playerId = null): array
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
            WHERE f.status = 'returning'
              AND f.return_at IS NOT NULL
              AND f.return_at <= :now
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
            if ($movement->getMission() !== FleetMission::Transport) {
                $this->completeNonTransportArrival($movement, $processedAt);
                $this->pdo->commit();

                return;
            }

            $payload = $movement->getPayload();
            $cargo = is_array($payload['cargo'] ?? null) ? $payload['cargo'] : [];
            $resources = is_array($cargo['resources'] ?? null) ? $cargo['resources'] : [];
            $destinationPlanetId = $movement->getDestinationPlanetId()
                ?? (isset($payload['destination_planet_id']) ? (int)$payload['destination_planet_id'] : null);

            $requested = [
                'metal' => max(0, (int)($resources['metal'] ?? 0)),
                'crystal' => max(0, (int)($resources['crystal'] ?? 0)),
                'hydrogen' => max(0, (int)($resources['hydrogen'] ?? 0)),
            ];

            $delivered = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0];
            $remaining = $requested;

            if ($destinationPlanetId !== null && array_sum($requested) > 0) {
                $planet = $this->lockPlanetResources($destinationPlanetId);
                if ($planet !== null) {
                    [$planet, $delivered, $remaining] = $this->transferResourcesToPlanet($planet, $requested);
                    $this->updatePlanetResources($destinationPlanetId, $planet);
                }
            }

            $deliveredTotal = array_sum($delivered);
            $remainingTotal = array_sum($remaining);

            $cargo['resources'] = $requested;
            $cargo['delivered'] = $delivered;
            $cargo['remaining'] = $remaining;
            $cargo['delivered_total'] = $deliveredTotal;
            $cargo['remaining_total'] = $remainingTotal;
            $cargo['delivery_complete'] = $remainingTotal === 0;
            $cargo['delivered_at'] = $processedAt->format(DATE_ATOM);
            $payload['cargo'] = $cargo;
            if ($destinationPlanetId !== null) {
                $payload['destination_planet_id'] = $destinationPlanetId;
            }

            $travelSeconds = max(0, $movement->getTravelTimeSeconds());
            $existingReturnAt = $movement->getReturnAt();
            $scheduledArrival = $movement->getArrivalAt();
            $shouldRecalculateReturnAt = $existingReturnAt === null;

            if ($scheduledArrival !== null && $processedAt > $scheduledArrival) {
                $shouldRecalculateReturnAt = true;
            }

            $returnDateTime = $shouldRecalculateReturnAt
                ? $this->calculateReturnAt($processedAt, $travelSeconds)
                : $existingReturnAt;
            $returnAt = $returnDateTime?->format('Y-m-d H:i:s');

            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $update = $this->pdo->prepare('UPDATE fleets SET status = :status, mission_payload = :payload, arrival_at = :arrivalAt, return_at = :returnAt, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'status' => FleetStatus::Returning->value,
                'payload' => $payloadJson,
                'arrivalAt' => $processedAt->format('Y-m-d H:i:s'),
                'returnAt' => $returnAt,
                'id' => $movement->getId(),
            ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function completeReturn(FleetMovement $movement, DateTimeImmutable $processedAt): void
    {
        $this->pdo->beginTransaction();

        try {
            $payload = $movement->getPayload();
            $sourceFleetId = isset($payload['source_fleet_id']) ? (int)$payload['source_fleet_id'] : null;
            $reuseFleet = $sourceFleetId === $movement->getId();

            if (!$reuseFleet) {
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
            }

            $cargo = is_array($payload['cargo'] ?? null) ? $payload['cargo'] : [];
            $remaining = is_array($cargo['remaining'] ?? null) ? $cargo['remaining'] : [];
            $toReturn = [
                'metal' => max(0, (int)($remaining['metal'] ?? 0)),
                'crystal' => max(0, (int)($remaining['crystal'] ?? 0)),
                'hydrogen' => max(0, (int)($remaining['hydrogen'] ?? 0)),
            ];

            $returned = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0];
            $overflow = $toReturn;

            if (array_sum($toReturn) > 0) {
                $planet = $this->lockPlanetResources($movement->getOriginPlanetId());
                if ($planet !== null) {
                    [$planet, $returned, $overflow] = $this->transferResourcesToPlanet($planet, $toReturn);
                    $this->updatePlanetResources($movement->getOriginPlanetId(), $planet);
                }
            }

            $cargo['returned'] = $returned;
            $cargo['remaining'] = $overflow;
            $cargo['returned_total'] = array_sum($returned);
            $cargo['remaining_total'] = array_sum($overflow);
            $cargo['return_complete'] = array_sum($overflow) === 0;
            $cargo['returned_at'] = $processedAt->format(DATE_ATOM);
            $payload['cargo'] = $cargo;
            $payload['completed_at'] = $processedAt->format(DATE_ATOM);
            $payload['fleet_restored_at'] = $processedAt->format(DATE_ATOM);

            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $isReusedFleet = $reuseFleet;
            $status = $isReusedFleet ? FleetStatus::Idle : FleetStatus::Completed;
            $returnAt = $isReusedFleet ? null : $movement->getReturnAt();
            $returnAtValue = $returnAt?->format('Y-m-d H:i:s');

            $update = $this->pdo->prepare('UPDATE fleets SET status = :status, mission_type = :mission, destination_planet_id = NULL, mission_payload = :payload, return_at = :returnAt, arrival_at = :arrivalAt, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'status' => $status->value,
                'mission' => FleetMission::Idle->value,
                'payload' => $payloadJson,
                'returnAt' => $returnAtValue,
                'arrivalAt' => $processedAt->format('Y-m-d H:i:s'),
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
    private function calculateReturnAt(DateTimeImmutable $arrivalAt, int $travelTimeSeconds): DateTimeImmutable
    {
        $seconds = max(0, $travelTimeSeconds);

        return $arrivalAt->add(new DateInterval('PT' . $seconds . 'S'));
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
        array $composition,
        array $payload = []
    ): int {
        $payloadData = $payload;
        $payloadData['destination'] = $destinationCoordinates->toArray();
        $payloadData['composition'] = $composition;
        if (!isset($payloadData['cargo']['fuel'])) {
            $payloadData['cargo']['fuel'] = $fuelConsumed;
        }

        $payloadJson = json_encode(
            $payloadData,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $returnAt = $this->calculateReturnAt($arrivalAt, $travelTimeSeconds);

        $stmt = $this->pdo->prepare('INSERT INTO fleets (player_id, origin_planet_id, destination_planet_id, mission_type, status, mission_payload, departure_at, arrival_at, return_at, travel_time_seconds, fuel_consumed, created_at, updated_at) VALUES (:player, :origin, :destination, :mission, :status, :payload, :departure, :arrival, :return, :travel, :fuel, NOW(), NOW())');
        $stmt->execute([
            'player' => $playerId,
            'origin' => $originPlanetId,
            'destination' => $destinationPlanetId,
            'mission' => $mission->value,
            'status' => $status->value,
            'payload' => $payloadJson,
            'departure' => $departureAt->format('Y-m-d H:i:s'),
            'arrival' => $arrivalAt->format('Y-m-d H:i:s'),
            'return' => $returnAt->format('Y-m-d H:i:s'),
            'travel' => $travelTimeSeconds,
            'fuel' => $fuelConsumed,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * @param array<string, int> $composition
     * @param array<string, mixed> $payload
     */
    private function activateFleetMission(
        int $fleetId,
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
        array $composition,
        array $payload
    ): int {
        $query = 'SELECT id FROM fleets WHERE id = :id AND player_id = :player AND origin_planet_id = :planet'
            . ' AND destination_planet_id IS NULL AND mission_type = :mission AND status IN (\'idle\',\'holding\')'
            . $this->forUpdateClause();

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            'id' => $fleetId,
            'player' => $playerId,
            'planet' => $originPlanetId,
            'mission' => FleetMission::Idle->value,
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
            throw new RuntimeException('Flotte indisponible pour le lancement de mission.');
        }

        $payloadData = $payload;
        $payloadData['destination'] = $destinationCoordinates->toArray();
        $payloadData['composition'] = $composition;
        $payloadData['source_fleet_id'] = $fleetId;
        if (!isset($payloadData['cargo']['fuel'])) {
            $payloadData['cargo']['fuel'] = $fuelConsumed;
        }

        $payloadJson = json_encode(
            $payloadData,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $returnAt = $this->calculateReturnAt($arrivalAt, $travelTimeSeconds);

        $update = $this->pdo->prepare('UPDATE fleets SET mission_type = :mission, status = :status, destination_planet_id = :destination,'
            . ' mission_payload = :payload, departure_at = :departure, arrival_at = :arrival, return_at = :return,'
            . ' travel_time_seconds = :travel, fuel_consumed = :fuel, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'mission' => $mission->value,
            'status' => $status->value,
            'destination' => $destinationPlanetId,
            'payload' => $payloadJson,
            'departure' => $departureAt->format('Y-m-d H:i:s'),
            'arrival' => $arrivalAt->format('Y-m-d H:i:s'),
            'return' => $returnAt->format('Y-m-d H:i:s'),
            'travel' => $travelTimeSeconds,
            'fuel' => $fuelConsumed,
            'id' => $fleetId,
        ]);

        return $fleetId;
    }

    private function completeNonTransportArrival(FleetMovement $movement, DateTimeImmutable $processedAt): void
    {
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

        $update = $this->pdo->prepare('UPDATE fleets SET status = :status, mission_type = :mission, destination_planet_id = NULL, mission_payload = :payload, return_at = :processedAt, arrival_at = :arrivalAt, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'status' => FleetStatus::Completed->value,
            'mission' => FleetMission::Idle->value,
            'payload' => $payload,
            'processedAt' => $processedAt->format('Y-m-d H:i:s'),
            'arrivalAt' => $processedAt->format('Y-m-d H:i:s'),
            'id' => $movement->getId(),
        ]);
    }

    private function lockPlanetResources(int $planetId): ?array
    {
        $query = 'SELECT metal, crystal, hydrogen, energy, metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity FROM planets WHERE id = :id' . $this->forUpdateClause();
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(['id' => $planetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'metal' => (int)$row['metal'],
            'crystal' => (int)$row['crystal'],
            'hydrogen' => (int)$row['hydrogen'],
            'energy' => (int)$row['energy'],
            'metal_capacity' => (int)$row['metal_capacity'],
            'crystal_capacity' => (int)$row['crystal_capacity'],
            'hydrogen_capacity' => (int)$row['hydrogen_capacity'],
            'energy_capacity' => (int)$row['energy_capacity'],
        ];
    }

    private function updatePlanetResources(int $planetId, array $planet): void
    {
        $stmt = $this->pdo->prepare('UPDATE planets SET metal = :metal, crystal = :crystal, hydrogen = :hydrogen, energy = :energy, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'metal' => (int)$planet['metal'],
            'crystal' => (int)$planet['crystal'],
            'hydrogen' => (int)$planet['hydrogen'],
            'energy' => (int)$planet['energy'],
            'id' => $planetId,
        ]);
    }

    /**
     * @param array<string, int> $planet
     * @param array<string, int> $requested
     *
     * @return array{0: array<string, int>, 1: array<string, int>, 2: array<string, int>}
     */
    private function transferResourcesToPlanet(array $planet, array $requested): array
    {
        $delivered = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0];
        $remaining = ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0];

        foreach (['metal', 'crystal', 'hydrogen'] as $resource) {
            $amount = max(0, (int)($requested[$resource] ?? 0));
            if ($amount <= 0) {
                $remaining[$resource] = 0;
                continue;
            }

            $current = (int)($planet[$resource] ?? 0);
            $capacityKey = $resource . '_capacity';
            $capacity = (int)($planet[$capacityKey] ?? $current);
            $space = max(0, $capacity - $current);
            $deliveredAmount = min($amount, $space);
            $planet[$resource] = $current + $deliveredAmount;
            $delivered[$resource] = $deliveredAmount;
            $remaining[$resource] = $amount - $deliveredAmount;
        }

        return [$planet, $delivered, $remaining];
    }

    private function forUpdateClause(): string
    {
        static $clause;

        if ($clause !== null) {
            return $clause;
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $clause = $driver === 'sqlite' ? '' : ' FOR UPDATE';

        return $clause;
    }

    private function ensureGarrisonFleet(int $playerId, int $planetId): int
    {
        $query = 'SELECT id FROM fleets WHERE player_id = :player AND origin_planet_id = :planet AND mission_type = :mission AND status = :status AND destination_planet_id IS NULL LIMIT 1';
        $forUpdate = $this->forUpdateClause();
        if ($forUpdate !== '') {
            $query .= $forUpdate;
        }

        $stmt = $this->pdo->prepare($query);
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
