<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Enum\FleetMission;
use App\Domain\Enum\FleetStatus;
use App\Domain\ValueObject\Coordinates;
use DateTimeImmutable;

final class FleetMovement
{
    /**
     * @param array<string, int> $composition
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly int $id,
        private readonly int $playerId,
        private readonly int $originPlanetId,
        private readonly ?int $destinationPlanetId,
        private readonly Coordinates $origin,
        private readonly Coordinates $destination,
        private readonly FleetMission $mission,
        private readonly FleetStatus $status,
        private readonly array $composition,
        private readonly DateTimeImmutable $departureAt,
        private readonly ?DateTimeImmutable $arrivalAt,
        private readonly ?DateTimeImmutable $returnAt,
        private readonly int $travelTimeSeconds,
        private readonly int $fuelConsumed,
        private readonly array $payload = []
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    public function getOriginPlanetId(): int
    {
        return $this->originPlanetId;
    }

    public function getDestinationPlanetId(): ?int
    {
        return $this->destinationPlanetId;
    }

    public function getOrigin(): Coordinates
    {
        return $this->origin;
    }

    public function getDestination(): Coordinates
    {
        return $this->destination;
    }

    public function getMission(): FleetMission
    {
        return $this->mission;
    }

    public function getStatus(): FleetStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, int>
     */
    public function getComposition(): array
    {
        return $this->composition;
    }

    public function getDepartureAt(): DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function getArrivalAt(): ?DateTimeImmutable
    {
        return $this->arrivalAt;
    }

    public function getReturnAt(): ?DateTimeImmutable
    {
        return $this->returnAt;
    }

    public function getTravelTimeSeconds(): int
    {
        return $this->travelTimeSeconds;
    }

    public function getFuelConsumed(): int
    {
        return $this->fuelConsumed;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
