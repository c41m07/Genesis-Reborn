<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

class ShipBuildJob
{
    public function __construct(
        private readonly int $id,
        private readonly int $planetId,
        private readonly string $shipKey,
        private readonly int $quantity,
        private readonly DateTimeImmutable $endsAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPlanetId(): int
    {
        return $this->planetId;
    }

    public function getShipKey(): string
    {
        return $this->shipKey;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }
}
