<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Infrastructure\Config\BuildingConfig;
use InvalidArgumentException;

final class ResourceEffectFactory
{
    /**
     * @param iterable<BuildingConfig> $buildingConfigs
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fromBuildingConfig(iterable $buildingConfigs): array
    {
        $effects = [];

        foreach ($buildingConfigs as $config) {
            if (!$config instanceof BuildingConfig) {
                throw new InvalidArgumentException('ResourceEffectFactory expects instances of BuildingConfig.');
            }

            $key = $config->getKey();
            $effect = [];
            $affects = $config->getAffects();

            $productionBase = (float) $config->getProductionBase();
            $productionGrowth = $config->getProductionGrowth();
            $energyUseBase = (float) $config->getEnergyUseBase();
            $energyUseGrowth = $config->getEnergyUseGrowth();
            $energyUseLinear = $config->isEnergyUseLinear();

            if (in_array($affects, ['metal', 'crystal', 'hydrogen'], true) && $productionBase > 0.0) {
                $effect['produces'][$affects] = [
                    'base' => $productionBase,
                    'growth' => $productionGrowth,
                ];
            } elseif ($affects === 'energy' && $productionBase > 0.0) {
                $effect['energy']['production'] = [
                    'base' => $productionBase,
                    'growth' => $productionGrowth,
                ];
            }

            if ($energyUseBase !== 0.0) {
                $effect['energy']['consumption'] = [
                    'base' => $energyUseBase,
                    'growth' => $energyUseGrowth,
                ];
                if ($energyUseLinear) {
                    $effect['energy']['consumption']['linear'] = true;
                }
            }

            $storage = $config->getStorage();
            if ($storage !== []) {
                foreach ($storage as $resourceKey => $storageConfig) {
                    $effect['storage'][$resourceKey] = [
                        'base' => (float) ($storageConfig['base'] ?? 0.0),
                        'growth' => (float) ($storageConfig['growth'] ?? 1.0),
                    ];
                }
            }

            $upkeep = $config->getUpkeep();
            if ($upkeep !== []) {
                foreach ($upkeep as $resourceKey => $upkeepConfig) {
                    $consumption = [
                        'base' => (float) ($upkeepConfig['base'] ?? 0.0),
                        'growth' => (float) ($upkeepConfig['growth'] ?? 1.0),
                    ];

                    if (!empty($upkeepConfig['linear'])) {
                        $consumption['linear'] = true;
                    }

                    $effect['consumes'][$resourceKey] = $consumption;
                }
            }

            if ($effect !== []) {
                $effects[$key] = $effect;
            }
        }

        return $effects;
    }
}
