<?php

namespace App\Domain\Service;

use App\Domain\Entity\BuildingDefinition;

class BuildingCalculator
{
    /**
     * @return array<string, int>
     */
    public function nextCost(BuildingDefinition $definition, int $currentLevel): array
    {
        $costs = [];
        foreach ($definition->getBaseCost() as $resource => $baseCost) {
            $costs[$resource] = (int) round($baseCost * pow($definition->getGrowthCost(), $currentLevel));
        }

        return $costs;
    }

    public function nextTime(BuildingDefinition $definition, int $currentLevel): int
    {
        return (int) round($definition->getBaseTime() * pow($definition->getGrowthTime(), $currentLevel));
    }

    public function productionAt(BuildingDefinition $definition, int $level): int
    {
        if ($level <= 0) {
            return 0;
        }

        return (int) round($definition->getProductionBase() * pow($definition->getProductionGrowth(), $level - 1));
    }

    public function energyUseAt(BuildingDefinition $definition, int $level): int
    {
        if ($level <= 0) {
            return 0;
        }

        $base = $definition->getEnergyUseBase();
        $growth = $definition->getEnergyUseGrowth();
        $value = $base * pow($growth, $level);

        if ($definition->isEnergyUseLinear()) {
            $value *= $level;
        }

        return (int) round($value);
    }

    /**
     * @return array<string, int>
     */
    public function storageAt(BuildingDefinition $definition, int $level): array
    {
        $storage = [];
        if ($level <= 0) {
            return $storage;
        }

        foreach ($definition->getStorageConfig() as $resource => $config) {
            $base = (float) ($config['base'] ?? 0);
            $growth = (float) ($config['growth'] ?? 1.0);
            if ($base <= 0) {
                continue;
            }

            $storage[$resource] = (int) round($base * pow($growth, $level - 1));
        }

        return $storage;
    }

    /**
     * @return array<string, int>
     */
    public function cumulativeCost(BuildingDefinition $definition, int $targetLevel): array
    {
        $totals = [];
        if ($targetLevel <= 0) {
            return $totals;
        }

        foreach ($definition->getBaseCost() as $resource => $base) {
            $sum = 0.0;
            for ($i = 0; $i < $targetLevel; $i++) {
                $sum += $base * pow($definition->getGrowthCost(), $i);
            }
            $totals[$resource] = (int) round($sum);
        }

        return $totals;
    }

    /**
     * @param array<string, int> $buildingLevels
     * @param array<string, int> $researchLevels
     *
     * @return array{ok: bool, missing: array<int, array{type: string, key: string, label: string, level: int, current: int}>}
     */
    public function checkRequirements(
        BuildingDefinition $definition,
        array $buildingLevels,
        array $researchLevels,
        array $researchCatalog = []
    ): array {
        $requirements = $definition->getRequirements();
        $missing = [];

        foreach ($requirements['buildings'] ?? [] as $key => $requiredLevel) {
            $currentLevel = (int) ($buildingLevels[$key] ?? 0);
            if ($currentLevel < $requiredLevel) {
                $missing[] = [
                    'type' => 'building',
                    'key' => $key,
                    'label' => $key,
                    'level' => (int) $requiredLevel,
                    'current' => $currentLevel,
                ];
            }
        }

        foreach ($requirements['research'] ?? [] as $key => $requiredLevel) {
            $currentLevel = (int) ($researchLevels[$key] ?? 0);
            $missing[] = $currentLevel < $requiredLevel ? [
                'type' => 'research',
                'key' => $key,
                'label' => $researchCatalog[$key]['label'] ?? $key,
                'level' => (int) $requiredLevel,
                'current' => $currentLevel,
            ] : null;
        }

        $missing = array_values(array_filter($missing));

        return [
            'ok' => empty($missing),
            'missing' => $missing,
        ];
    }
}
