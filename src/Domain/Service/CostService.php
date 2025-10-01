<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Config\BalanceConfig;
use App\Domain\ValueObject\ResourceCost;

class CostService
{
    public function __construct(private readonly BalanceConfig $balanceConfig = new BalanceConfig())
    {
    }

    /**
     * @param ResourceCost|array<string, int|float> $baseCost
     *
     * @return ResourceCost|array<string, int>
     */
    public function nextLevelCost(ResourceCost|array $baseCost, float $growthFactor, int $currentLevel): ResourceCost|array
    {
        $costs = [];
        $baseValues = $baseCost instanceof ResourceCost ? $baseCost->toArray() : $baseCost;

        foreach ($baseValues as $resource => $value) {
            $costs[$resource] = (int)round(((float)$value) * pow($growthFactor, $currentLevel));
        }

        $result = ResourceCost::fromArray($costs);

        return $baseCost instanceof ResourceCost ? $result : $result->toArray();
    }

    /**
     * @param ResourceCost|array<string, int|float> $baseCost
     *
     * @return ResourceCost|array<string, int>
     */
    public function cumulativeCost(ResourceCost|array $baseCost, float $growthFactor, int $startLevel, int $levels): ResourceCost|array
    {
        $totals = [];
        $baseValues = $baseCost instanceof ResourceCost ? $baseCost->toArray() : $baseCost;

        foreach ($baseValues as $resource => $value) {
            $totals[$resource] = 0.0;
        }

        for ($i = 0; $i < $levels; $i++) {
            $levelIndex = $startLevel + $i;
            foreach ($baseValues as $resource => $value) {
                $totals[$resource] += ((float)$value) * pow($growthFactor, $levelIndex);
            }
        }

        $mapped = array_map(static fn (float $value) => (int)round($value), $totals);
        $result = ResourceCost::fromArray($mapped);

        return $baseCost instanceof ResourceCost ? $result : $result->toArray();
    }

    public function scaledDuration(int $baseDuration, float $growthFactor, int $currentLevel, float $speedModifier = 1.0): int
    {
        $speedModifier = max($this->balanceConfig->getMinimumSpeedModifier(), $speedModifier);

        return (int)max(1, round($baseDuration * pow($growthFactor, $currentLevel) / $speedModifier));
    }

    /**
     * @param ResourceCost|array<string, int|float> $cost
     *
     * @return ResourceCost|array<string, int>
     */
    public function applyDiscount(ResourceCost|array $cost, float $discount): ResourceCost|array
    {
        $discount = max(0.0, min($this->balanceConfig->getMaximumDiscount(), $discount));

        $result = [];
        $values = $cost instanceof ResourceCost ? $cost->toArray() : $cost;

        foreach ($values as $resource => $value) {
            $result[$resource] = (int)max(0, round(((float)$value) * (1 - $discount)));
        }

        $resourceCost = ResourceCost::fromArray($result);

        return $cost instanceof ResourceCost ? $resourceCost : $resourceCost->toArray();
    }

}
