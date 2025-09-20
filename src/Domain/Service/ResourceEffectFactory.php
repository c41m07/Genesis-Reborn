<?php

namespace App\Domain\Service;

final class ResourceEffectFactory
{
    /**
     * @param array<string, array<string, mixed>> $buildingConfig
     *
     * @return array<string, array<string, mixed>>
     */
    public static function fromBuildingConfig(array $buildingConfig): array
    {
        $effects = [];

        foreach ($buildingConfig as $key => $data) {
            if (!is_array($data)) {
                continue;
            }

            $effect = [];
            $affects = $data['affects'] ?? null;

            $productionBase = (float) ($data['prod_base'] ?? 0);
            $productionGrowth = (float) ($data['prod_growth'] ?? 1);
            $energyUseBase = (float) ($data['energy_use_base'] ?? 0);
            $energyUseGrowth = (float) ($data['energy_use_growth'] ?? 1);
            $energyUseLinear = !empty($data['energy_use_linear']);

            if (in_array($affects, ['metal', 'crystal', 'hydrogen'], true) && $productionBase > 0) {
                $effect['produces'][$affects] = [
                    'base' => $productionBase,
                    'growth' => $productionGrowth,
                ];
            } elseif ($affects === 'energy' && $productionBase > 0) {
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

            if (!empty($data['storage']) && is_array($data['storage'])) {
                foreach ($data['storage'] as $resourceKey => $storageConfig) {
                    if (!is_array($storageConfig)) {
                        continue;
                    }

                    $effect['storage'][$resourceKey] = [
                        'base' => (float) ($storageConfig['base'] ?? 0),
                        'growth' => (float) ($storageConfig['growth'] ?? 1),
                    ];
                }
            }

            if (!empty($data['upkeep']) && is_array($data['upkeep'])) {
                foreach ($data['upkeep'] as $resourceKey => $upkeepConfig) {
                    if (!is_array($upkeepConfig)) {
                        continue;
                    }

                    $consumption = [
                        'base' => (float) ($upkeepConfig['base'] ?? 0),
                        'growth' => (float) ($upkeepConfig['growth'] ?? 1),
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
