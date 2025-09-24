<?php

declare(strict_types=1);

namespace App\Domain\Entity;

class BuildingDefinition
{
    /**
     * @param array<string, int> $baseCost
     * @param array{buildings?: array<string, int>, research?: array<string, int>} $requirements
     * @param array{base?: float, growth?: float, linear?: bool, max?: float} $shipBuildSpeedBonus
     * @param array<string, mixed> $researchSpeedBonus
     * @param array<string, array{base: float, growth: float}> $storage
     * @param array<string, array{base?: float, growth?: float, linear?: bool}> $upkeep
     * @param array<string, mixed> $constructionSpeedBonus
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly array $baseCost,
        private readonly float $growthCost,
        private readonly int $baseTime,
        private readonly float $growthTime,
        private readonly int $productionBase,
        private readonly float $productionGrowth,
        private readonly int $energyUseBase,
        private readonly float $energyUseGrowth,
        private readonly bool $energyUseLinear,
        private readonly string $affects,
        private readonly array $requirements = [],
        private readonly ?string $image = null,
        private readonly array $shipBuildSpeedBonus = [],
        private readonly array $researchSpeedBonus = [],
        private readonly array $storage = [],
        private readonly array $upkeep = [],
        private readonly array $constructionSpeedBonus = []
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

    /** @return array<string, int> */
    public function getBaseCost(): array
    {
        return $this->baseCost;
    }

    public function getGrowthCost(): float
    {
        return $this->growthCost;
    }

    public function getBaseTime(): int
    {
        return $this->baseTime;
    }

    public function getGrowthTime(): float
    {
        return $this->growthTime;
    }

    public function getProductionBase(): int
    {
        return $this->productionBase;
    }

    public function getProductionGrowth(): float
    {
        return $this->productionGrowth;
    }

    public function getEnergyUseBase(): int
    {
        return $this->energyUseBase;
    }

    public function getEnergyUseGrowth(): float
    {
        return $this->energyUseGrowth;
    }

    public function isEnergyUseLinear(): bool
    {
        return $this->energyUseLinear;
    }

    public function getAffects(): string
    {
        return $this->affects;
    }

    /** @return array{buildings?: array<string, int>, research?: array<string, int>} */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * @return array{base?: float, growth?: float, linear?: bool, max?: float}
     */
    public function getShipBuildSpeedBonusConfig(): array
    {
        return $this->shipBuildSpeedBonus;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResearchSpeedBonusConfig(): array
    {
        return $this->researchSpeedBonus;
    }

    /**
     * @return array<string, array{base: float, growth: float}>
     */
    public function getStorageConfig(): array
    {
        return $this->storage;
    }

    /**
     * @return array<string, array{base?: float, growth?: float, linear?: bool}>
     */
    public function getUpkeepConfig(): array
    {
        return $this->upkeep;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConstructionSpeedBonusConfig(): array
    {
        return $this->constructionSpeedBonus;

    }
}
