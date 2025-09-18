<?php

namespace App\Domain\Entity;

class ResearchDefinition
{
    /**
     * @param array<string, int> $baseCost
     * @param array<string, int> $requires
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $category,
        private readonly string $description,
        private readonly array $baseCost,
        private readonly int $baseTime,
        private readonly float $growthCost,
        private readonly float $growthTime,
        private readonly int $maxLevel,
        private readonly array $requires,
        private readonly int $requiresLab,
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

    public function getBaseTime(): int
    {
        return $this->baseTime;
    }

    public function getGrowthCost(): float
    {
        return $this->growthCost;
    }

    public function getGrowthTime(): float
    {
        return $this->growthTime;
    }

    public function getMaxLevel(): int
    {
        return $this->maxLevel;
    }

    /**
     * @return array<string, int>
     */
    public function getRequires(): array
    {
        return $this->requires;
    }

    public function getRequiresLab(): int
    {
        return $this->requiresLab;
    }

    public function getImage(): string
    {
        return $this->image;
    }
}
