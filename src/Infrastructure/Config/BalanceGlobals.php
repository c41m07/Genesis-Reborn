<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final class BalanceGlobals
{
    /** @var array<string, int> */
    private array $initialResources = [];

    /** @var array<string, int> */
    private array $initialCapacities = [];

    /** @var array<string, int> */
    private array $homeworldDefaultStats = [
        'diameter' => 12000,
        'temperature_min' => -20,
        'temperature_max' => 40,
    ];

    /** @var array<int, array<string, int>> */
    private array $homeworldPositionStats = [];

    private int $homeworldMinPosition = 1;

    private int $homeworldMaxPosition = 9;

    private float $homeworldDefaultVariation = 0.0;

    /** @var array<string, float> */
    private array $homeworldVariationOverrides = [];

    /**
     * @param array<string, int|float> $initialResources
     * @param array<string, int|float> $initialCapacities
     * @param array<string, mixed> $homeworld
     */
    public function __construct(array $initialResources = [], array $initialCapacities = [], array $homeworld = [])
    {
        foreach ($initialResources as $resource => $value) {
            $this->initialResources[$resource] = (int) round((float) $value);
        }

        foreach ($initialCapacities as $resource => $value) {
            $this->initialCapacities[$resource] = (int) round((float) $value);
        }

        $this->parseHomeworldConfig($homeworld);
    }

    /** @return array<string, int> */
    public function getInitialResources(): array
    {
        return $this->initialResources;
    }

    public function getInitialResource(string $resource): int
    {
        return $this->initialResources[$resource] ?? 0;
    }

    /** @return array<string, int> */
    public function getInitialCapacities(): array
    {
        return $this->initialCapacities;
    }

    public function getInitialCapacity(string $resource): int
    {
        return $this->initialCapacities[$resource] ?? 0;
    }

    public function getHomeworldMinPosition(): int
    {
        return $this->homeworldMinPosition;
    }

    public function getHomeworldMaxPosition(): int
    {
        return $this->homeworldMaxPosition;
    }

    /**
     * @return array{diameter: int, temperature_min: int, temperature_max: int}
     */
    public function getHomeworldBaseStats(int $position): array
    {
        $stats = $this->homeworldPositionStats[$position] ?? [];

        return [
            'diameter' => isset($stats['diameter']) ? $stats['diameter'] : $this->homeworldDefaultStats['diameter'],
            'temperature_min' => isset($stats['temperature_min']) ? $stats['temperature_min'] : $this->homeworldDefaultStats['temperature_min'],
            'temperature_max' => isset($stats['temperature_max']) ? $stats['temperature_max'] : $this->homeworldDefaultStats['temperature_max'],
        ];
    }

    public function getHomeworldVariation(string $stat): float
    {
        return $this->homeworldVariationOverrides[$stat] ?? $this->homeworldDefaultVariation;
    }

    /**
     * @param array<string, mixed> $homeworld
     */
    private function parseHomeworldConfig(array $homeworld): void
    {
        if (isset($homeworld['defaults']) && is_array($homeworld['defaults'])) {
            foreach (array_keys($this->homeworldDefaultStats) as $key) {
                if (array_key_exists($key, $homeworld['defaults'])) {
                    $this->homeworldDefaultStats[$key] = (int) round((float) $homeworld['defaults'][$key]);
                }
            }
        }

        if (isset($homeworld['min_position'])) {
            $this->homeworldMinPosition = max(1, (int) $homeworld['min_position']);
        }

        if (isset($homeworld['max_position'])) {
            $this->homeworldMaxPosition = max($this->homeworldMinPosition, (int) $homeworld['max_position']);
        } else {
            $this->homeworldMaxPosition = max($this->homeworldMinPosition, $this->homeworldMaxPosition);
        }

        if (isset($homeworld['positions']) && is_array($homeworld['positions'])) {
            foreach ($homeworld['positions'] as $position => $stats) {
                if (!is_array($stats)) {
                    continue;
                }

                $index = (int) $position;
                $this->homeworldPositionStats[$index] = [
                    'diameter' => isset($stats['diameter']) ? (int) round((float) $stats['diameter']) : $this->homeworldDefaultStats['diameter'],
                    'temperature_min' => isset($stats['temperature_min']) ? (int) round((float) $stats['temperature_min']) : $this->homeworldDefaultStats['temperature_min'],
                    'temperature_max' => isset($stats['temperature_max']) ? (int) round((float) $stats['temperature_max']) : $this->homeworldDefaultStats['temperature_max'],
                ];
            }
        }

        if (isset($homeworld['variation_percent'])) {
            $variation = $homeworld['variation_percent'];

            if (is_array($variation)) {
                if (array_key_exists('default', $variation)) {
                    $this->homeworldDefaultVariation = max(0.0, (float) $variation['default']);
                }

                foreach ($variation as $key => $value) {
                    if ($key === 'default') {
                        continue;
                    }

                    $this->homeworldVariationOverrides[$key] = max(0.0, (float) $value);
                }
            } else {
                $this->homeworldDefaultVariation = max(0.0, (float) $variation);
            }
        }

        if ($this->homeworldMaxPosition < $this->homeworldMinPosition) {
            $this->homeworldMaxPosition = $this->homeworldMinPosition;
        }
    }
}
