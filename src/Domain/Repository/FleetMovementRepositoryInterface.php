<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\FleetMovement;
use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\ValueObject\Coordinates;
use DateTimeImmutable;

interface FleetMovementRepositoryInterface
{
    /**
     * @param array<string, int> $composition
     */
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
    ): FleetMovement;

    /**
     * @return list<FleetMovement>
     */
    public function findActiveByOriginPlanet(int $planetId): array;

    /**
     * @return list<FleetMovement>
     */
    public function findArrivedMissions(DateTimeImmutable $now, ?int $playerId = null): array;

    public function completeArrival(FleetMovement $movement, DateTimeImmutable $processedAt): void;
}
