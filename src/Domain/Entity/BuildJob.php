<?php

namespace App\Domain\Entity;

use DateTimeImmutable;

class BuildJob
{
    public function __construct(
        private readonly int $id,
        private readonly int $planetId,
        private readonly string $buildingKey,
        private readonly int $targetLevel,
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

    public function getBuildingKey(): string
    {
        return $this->buildingKey;
    }

    public function getTargetLevel(): int
    {
        return $this->targetLevel;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }
}
