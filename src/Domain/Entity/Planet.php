<?php

namespace App\Domain\Entity;

class Planet
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private int $galaxy,
        private int $system,
        private int $position,
        private string $name,
        private int $metal,
        private int $crystal,
        private int $hydrogen,
        private int $energy,
        private int $metalPerHour,
        private int $crystalPerHour,
        private int $hydrogenPerHour,
        private int $energyPerHour
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGalaxy(): int
    {
        return $this->galaxy;
    }

    public function getSystem(): int
    {
        return $this->system;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array{galaxy: int, system: int, position: int}
     */
    public function getCoordinates(): array
    {
        return [
            'galaxy' => $this->galaxy,
            'system' => $this->system,
            'position' => $this->position,
        ];
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getMetal(): int
    {
        return $this->metal;
    }

    public function setMetal(int $metal): void
    {
        $this->metal = $metal;
    }

    public function getCrystal(): int
    {
        return $this->crystal;
    }

    public function setCrystal(int $crystal): void
    {
        $this->crystal = $crystal;
    }

    public function getHydrogen(): int
    {
        return $this->hydrogen;
    }

    public function setHydrogen(int $hydrogen): void
    {
        $this->hydrogen = $hydrogen;
    }

    public function getEnergy(): int
    {
        return $this->energy;
    }

    public function setEnergy(int $energy): void
    {
        $this->energy = $energy;
    }

    public function getMetalPerHour(): int
    {
        return $this->metalPerHour;
    }

    public function setMetalPerHour(int $value): void
    {
        $this->metalPerHour = $value;
    }

    public function getCrystalPerHour(): int
    {
        return $this->crystalPerHour;
    }

    public function setCrystalPerHour(int $value): void
    {
        $this->crystalPerHour = $value;
    }

    public function getHydrogenPerHour(): int
    {
        return $this->hydrogenPerHour;
    }

    public function setHydrogenPerHour(int $value): void
    {
        $this->hydrogenPerHour = $value;
    }

    public function getEnergyPerHour(): int
    {
        return $this->energyPerHour;
    }

    public function setEnergyPerHour(int $value): void
    {
        $this->energyPerHour = $value;
    }
}
