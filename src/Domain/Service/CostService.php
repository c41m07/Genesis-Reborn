<?php

namespace App\Domain\Service;

class CostService
{
    /**
     * @param array<string, int|float> $baseCost
     *
     * @return array<string, int>
     */
    public function nextLevelCost(array $baseCost, float $growthFactor, int $currentLevel): array
    {
        $costs = [];

        foreach ($baseCost as $resource => $value) {
            $costs[$resource] = (int) round(((float) $value) * pow($growthFactor, $currentLevel));
        }

        return $costs;
    }

    /**
     * @param array<string, int|float> $baseCost
     *
     * @return array<string, int>
     */
    public function cumulativeCost(array $baseCost, float $growthFactor, int $startLevel, int $levels): array
    {
        $totals = [];
        foreach ($baseCost as $resource => $value) {
            $totals[$resource] = 0.0;
        }

        for ($i = 0; $i < $levels; $i++) {
            $levelIndex = $startLevel + $i;
            foreach ($baseCost as $resource => $value) {
                $totals[$resource] += ((float) $value) * pow($growthFactor, $levelIndex);
            }
        }

        return array_map(static fn (float $value) => (int) round($value), $totals);
    }

    public function scaledDuration(int $baseDuration, float $growthFactor, int $currentLevel, float $speedModifier = 1.0): int
    {
        $speedModifier = max(0.01, $speedModifier);

        return (int) max(1, round($baseDuration * pow($growthFactor, $currentLevel) / $speedModifier));
    }

    /**
     * @param array<string, int|float> $cost
     *
     * @return array<string, int>
     */
    public function applyDiscount(array $cost, float $discount): array
    {
        $discount = max(0.0, min(0.95, $discount));

        $result = [];
        foreach ($cost as $resource => $value) {
            $result[$resource] = (int) max(0, round(((float) $value) * (1 - $discount)));
        }

        return $result;
    }
}
