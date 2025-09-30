<?php

declare(strict_types=1);

namespace App\Domain\Entity;

class ShipDefinition
{
    /**
     * @param array<string, int> $baseCost
     * @param array<string, int> $stats
     * @param array<string, int> $requiresResearch
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $category,
        private readonly string $role,
        private readonly string $description,
        private readonly array  $baseCost,
        private readonly int    $buildTime,
        private readonly array  $stats,
        private readonly array  $requiresResearch,
        private readonly string $image
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, int>
     */
    public function getBaseCost(): array
    {
        return $this->baseCost;
    }

    public function getBuildTime(): int
    {
        return $this->buildTime;
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * @return array<string, int>
     */
    public function getRequiresResearch(): array
    {
        return $this->requiresResearch;
    }

    public function getImage(): string
    {
        return $this->image;
    }
}
