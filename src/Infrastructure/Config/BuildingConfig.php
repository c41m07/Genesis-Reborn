<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use InvalidArgumentException;

final class BuildingConfig
{
    private string $key;

    private string $label;

    /** @var array<string, int> */
    private array $baseCost = [];

    private float $growthCost = 1.0;

    private int $baseTime = 0;

    private float $growthTime = 1.0;

    private int $productionBase = 0;

    private float $productionGrowth = 1.0;

    private int $energyUseBase = 0;

    private float $energyUseGrowth = 1.0;

    private bool $energyUseLinear = false;

    private string $affects = '';

    /** @var array<string, int> */
    private array $requiredBuildings = [];

    /** @var array<string, int> */
    private array $requiredResearch = [];

    private ?string $image = null;

    /** @var array{base?: float, per_level?: float, growth?: float, linear?: bool, max?: float} */
    private array $shipBuildSpeedBonus = [];

    /** @var array{base?: float, per_level?: float, growth?: float, linear?: bool, max?: float} */
    private array $researchSpeedBonus = [];

    /** @var array<string, array{base: float, growth: float}> */
    private array $storage = [];

    /** @var array<string, array{base: float, growth: float, linear?: bool}> */
    private array $upkeep = [];

    /** @var array{base?: float, per_level?: float, growth?: float, linear?: bool, max?: float} */
    private array $constructionSpeedBonus = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $key, array $data)
    {
        $this->key = $key;
        $this->label = (string) ($data['label'] ?? $key);

        $baseCost = $data['base_cost'] ?? [];
        if (!is_array($baseCost)) {
            throw new InvalidArgumentException(sprintf('Invalid base_cost definition for building "%s".', $key));
        }

        foreach ($baseCost as $resource => $value) {
            $this->baseCost[$resource] = (int) round((float) $value);
        }

        $this->growthCost = (float) ($data['growth_cost'] ?? 1.0);
        $this->baseTime = (int) round((float) ($data['base_time'] ?? 0));
        $this->growthTime = (float) ($data['growth_time'] ?? 1.0);
        $this->productionBase = (int) round((float) ($data['prod_base'] ?? 0));
        $this->productionGrowth = (float) ($data['prod_growth'] ?? 1.0);
        $this->energyUseBase = (int) round((float) ($data['energy_use_base'] ?? 0));
        $this->energyUseGrowth = (float) ($data['energy_use_growth'] ?? 1.0);
        $this->energyUseLinear = !empty($data['energy_use_linear']);
        $this->affects = (string) ($data['affects'] ?? '');

        if (!empty($data['image'])) {
            $this->image = (string) $data['image'];
        }

        $requires = $data['requires'] ?? [];
        if (!is_array($requires)) {
            throw new InvalidArgumentException(sprintf('Invalid requires definition for building "%s".', $key));
        }

        if (!empty($requires['buildings']) && is_array($requires['buildings'])) {
            foreach ($requires['buildings'] as $buildingKey => $level) {
                $this->requiredBuildings[$buildingKey] = max(0, (int) $level);
            }
        }

        if (!empty($requires['research']) && is_array($requires['research'])) {
            foreach ($requires['research'] as $researchKey => $level) {
                $this->requiredResearch[$researchKey] = max(0, (int) $level);
            }
        }

        $this->shipBuildSpeedBonus = $this->normalizeBonusConfig($data['ship_build_speed_bonus'] ?? null);
        $this->researchSpeedBonus = $this->normalizeBonusConfig($data['research_speed_bonus'] ?? null);
        $this->constructionSpeedBonus = $this->normalizeBonusConfig($data['construction_speed_bonus'] ?? null);

        $storageConfig = $data['storage'] ?? [];
        if (is_array($storageConfig)) {
            foreach ($storageConfig as $resourceKey => $config) {
                if (!is_array($config)) {
                    continue;
                }

                $this->storage[$resourceKey] = [
                    'base' => (float) ($config['base'] ?? 0.0),
                    'growth' => (float) ($config['growth'] ?? 1.0),
                ];
            }
        }

        $upkeepConfig = $data['upkeep'] ?? [];
        if (is_array($upkeepConfig)) {
            foreach ($upkeepConfig as $resourceKey => $config) {
                if (!is_array($config)) {
                    continue;
                }

                $normalized = [
                    'base' => (float) ($config['base'] ?? 0.0),
                    'growth' => (float) ($config['growth'] ?? 1.0),
                ];

                if (!empty($config['linear'])) {
                    $normalized['linear'] = true;
                }

                $this->upkeep[$resourceKey] = $normalized;
            }
        }
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

    /**
     * @return array{buildings: array<string, int>, research: array<string, int>}
     */
    public function getRequirements(): array
    {
        return [
            'buildings' => $this->requiredBuildings,
            'research' => $this->requiredResearch,
        ];
    }

    /** @return array<string, int> */
    public function getRequiredBuildings(): array
    {
        return $this->requiredBuildings;
    }

    /** @return array<string, int> */
    public function getRequiredResearch(): array
    {
        return $this->requiredResearch;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    /**
     * @return array{base?: float, per_level?: float, growth?: float, linear?: bool, max?: float}
     */
    public function getShipBuildSpeedBonus(): array
    {
        return $this->shipBuildSpeedBonus;
    }

    /**
     * @return array{base?: float, per_level?: float, growth?: float, linear?: bool, max?: float}
     */
    public function getResearchSpeedBonus(): array
    {
        return $this->researchSpeedBonus;
    }

    /**
     * @return array<string, array{base: float, growth: float}>
     */
    public function getStorage(): array
    {
        return $this->storage;
    }

    /**
     * @return array<string, array{base: float, growth: float, linear?: bool}>
     */
    public function getUpkeep(): array
    {
        return $this->upkeep;
    }

    /**
     * @return array{base?: float, per_level?: float, growth?: float, linear?: bool, max?: float}
     */
    public function getConstructionSpeedBonus(): array
    {
        return $this->constructionSpeedBonus;
    }

    /**
     * @param mixed $value
     *
     * @return array{base?: float, per_level?: float, growth?: float, linear?: bool, max?: float}
     */
    private function normalizeBonusConfig(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            $numeric = (float) $value;
            if ($numeric === 0.0) {
                return [];
            }

            return ['base' => $numeric];
        }

        $normalized = [];

        if (array_key_exists('base', $value)) {
            $normalized['base'] = (float) $value['base'];
        }

        if (array_key_exists('per_level', $value)) {
            $normalized['per_level'] = (float) $value['per_level'];
        }

        if (array_key_exists('growth', $value)) {
            $normalized['growth'] = (float) $value['growth'];
        }

        if (!empty($value['linear'])) {
            $normalized['linear'] = true;
        }

        if (array_key_exists('max', $value)) {
            $normalized['max'] = (float) $value['max'];
        }

        return $normalized;
    }
}
