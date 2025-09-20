<?php

namespace App\Domain\Service;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class ResourceTickService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $defaultEffects;

    /**
     * @param array<string, array<string, mixed>> $defaultEffects
     */
    public function __construct(array $defaultEffects = [])
    {
        $this->defaultEffects = $defaultEffects;
    }

    /**
     * @param array<int, array{
     *     planet_id?: int,
     *     player_id?: int,
     *     resources: array<string, int|float>,
     *     capacities: array<string, int|float>,
     *     last_tick?: DateTimeInterface,
     *     building_levels: array<string, int>,
     * }> $planetStates
     * @param DateTimeInterface $now
     * @param array<string, array<string, mixed>>|null $effectsOverride
     *
     * @return array<int, array{
     *     resources: array<string, int>,
     *     production_per_hour: array<string, int>,
     *     capacities: array<string, int>,
     *     energy: array{production: int, consumption: int, balance: int, available: int, ratio: float},
     *     elapsed_seconds: int,
     * }>
     */
    public function tick(array $planetStates, DateTimeInterface $now, ?array $effectsOverride = null): array
    {
        $effects = $effectsOverride ?? $this->defaultEffects;
        $results = [];

        foreach ($planetStates as $key => $state) {
            if (!isset($state['last_tick']) || !$state['last_tick'] instanceof DateTimeInterface) {
                throw new InvalidArgumentException('Planet state must define a last_tick DateTime.');
            }

            $lastTick = DateTimeImmutable::createFromInterface($state['last_tick']);
            $elapsedSeconds = max(0, $now->getTimestamp() - $lastTick->getTimestamp());

            $resourceTotals = [];
            foreach ($state['resources'] as $resourceKey => $value) {
                $resourceTotals[$resourceKey] = (float) $value;
            }

            $capacityTotals = [];
            foreach ($state['capacities'] as $resourceKey => $value) {
                $capacityTotals[$resourceKey] = (float) $value;
            }

            $productionRaw = [];
            $energyProduction = 0.0;
            $energyConsumption = 0.0;

            foreach ($state['building_levels'] as $buildingKey => $level) {
                if ($level <= 0) {
                    continue;
                }

                $effect = $effects[$buildingKey] ?? null;
                if ($effect === null) {
                    continue;
                }

                if (isset($effect['storage']) && is_array($effect['storage'])) {
                    foreach ($effect['storage'] as $resourceKey => $config) {
                        $capacityTotals[$resourceKey] = ($capacityTotals[$resourceKey] ?? 0.0)
                            + $this->valueForLevel($config, $level);
                    }
                }

                if (isset($effect['produces']) && is_array($effect['produces'])) {
                    foreach ($effect['produces'] as $resourceKey => $config) {
                        $amount = $this->valueForLevel($config, $level);
                        $productionRaw[$resourceKey] = ($productionRaw[$resourceKey] ?? 0.0) + $amount;

                        if ($resourceKey === 'energy') {
                            $energyProduction += $amount;
                        }
                    }
                }

                if (isset($effect['consumes']) && is_array($effect['consumes'])) {
                    foreach ($effect['consumes'] as $resourceKey => $config) {
                        $amount = $this->valueForLevel($config, $level);
                        if ($resourceKey === 'energy') {
                            $energyConsumption += $amount;
                            continue;
                        }

                        $productionRaw[$resourceKey] = ($productionRaw[$resourceKey] ?? 0.0) - $amount;
                    }
                }

                if (isset($effect['energy']) && is_array($effect['energy'])) {
                    $energyConfig = $effect['energy'];

                    if (isset($energyConfig['production'])) {
                        $energyProduction += $this->valueForLevel($energyConfig['production'], $level);
                    }

                    if (isset($energyConfig['consumption'])) {
                        $energyConsumption += $this->valueForLevel($energyConfig['consumption'], $level);
                    }
                }
            }

            $energyRatio = 1.0;
            if ($energyConsumption > 0.0 && $energyProduction < $energyConsumption) {
                $energyRatio = max(0.0, $energyProduction / $energyConsumption);
            }

            $secondsFactor = $elapsedSeconds / 3600;
            $adjustedProduction = [];

            foreach ($productionRaw as $resourceKey => $perHour) {
                if ($resourceKey === 'energy') {
                    continue;
                }

                $adjustedProduction[$resourceKey] = $perHour * $energyRatio;
            }

            foreach ($adjustedProduction as $resourceKey => $perHour) {
                $current = $resourceTotals[$resourceKey] ?? 0.0;
                $delta = $perHour * $secondsFactor;
                $newAmount = $current + $delta;

                $capacity = $capacityTotals[$resourceKey] ?? null;
                if ($capacity !== null && $capacity > 0) {
                    $newAmount = min($capacity, $newAmount);
                }

                $resourceTotals[$resourceKey] = max(0.0, $newAmount);
            }

            $energyPerHour = $energyProduction - $energyConsumption;
            $energyDelta = $energyPerHour * $secondsFactor;
            $currentEnergy = $resourceTotals['energy'] ?? 0.0;
            $energyCapacity = $capacityTotals['energy'] ?? null;
            $energyAvailable = $currentEnergy + $energyDelta;

            if ($energyCapacity !== null && $energyCapacity > 0) {
                $energyAvailable = min($energyCapacity, $energyAvailable);
            }

            $energyAvailable = max(0.0, $energyAvailable);
            $resourceTotals['energy'] = $energyAvailable;

            $finalResources = [];
            foreach ($resourceTotals as $resourceKey => $value) {
                $finalResources[$resourceKey] = (int) floor($value + 0.000001);
            }

            $finalCapacities = [];
            foreach ($capacityTotals as $resourceKey => $value) {
                $finalCapacities[$resourceKey] = (int) round($value);
            }

            $productionPerHour = [];
            foreach ($adjustedProduction as $resourceKey => $perHour) {
                $productionPerHour[$resourceKey] = (int) round($perHour);
            }

            $productionPerHour['energy'] = (int) round($energyPerHour);

            $results[$key] = [
                'resources' => $finalResources,
                'production_per_hour' => $productionPerHour,
                'capacities' => $finalCapacities,
                'energy' => [
                    'production' => (int) round($energyProduction),
                    'consumption' => (int) round($energyConsumption),
                    'balance' => (int) round($energyPerHour),
                    'available' => (int) floor($energyAvailable + 0.000001),
                    'ratio' => $energyRatio,
                ],
                'elapsed_seconds' => $elapsedSeconds,
            ];
        }

        return $results;
    }

    /**
     * @param array{base?: float|int, growth?: float|int, linear?: bool} $config
     */
    private function valueForLevel(array $config, int $level): float
    {
        if ($level <= 0) {
            return 0.0;
        }

        $base = (float) ($config['base'] ?? 0.0);
        $growth = isset($config['growth']) ? (float) $config['growth'] : 1.0;
        $value = $base * pow($growth, max(0, $level - 1));

        if (!empty($config['linear'])) {
            $value *= $level;
        }

        return $value;
    }
}
