<?php

namespace App\Infrastructure\Config;

use App\Domain\Config\BalanceConfig;
use App\Domain\Config\BalanceRoundingConfig;
use InvalidArgumentException;
use RuntimeException;

final class BalanceConfigLoader
{
    public function load(string $path): BalanceConfig
    {
        $config = $this->loadFile($path);

        return $this->fromArray($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function fromArray(array $config): BalanceConfig
    {
        $roundingTolerance = isset($config['rounding_tolerance'])
            ? (float) $config['rounding_tolerance']
            : 0.000001;

        $roundingConfig = [];
        if (isset($config['rounding']) && is_array($config['rounding'])) {
            $roundingConfig = $config['rounding'];
        }

        $energyConfig = [];
        if (isset($roundingConfig['energy']) && is_array($roundingConfig['energy'])) {
            $energyConfig = $roundingConfig['energy'];
        }

        $rounding = new BalanceRoundingConfig(
            $roundingTolerance,
            $this->extractMode($roundingConfig, ['resources', 'resource'], BalanceRoundingConfig::MODE_FLOOR),
            $this->extractMode($roundingConfig, ['capacities', 'capacity'], BalanceRoundingConfig::MODE_ROUND),
            $this->extractMode($roundingConfig, ['production', 'productions'], BalanceRoundingConfig::MODE_ROUND),
            $this->extractEnergyMode($energyConfig, BalanceRoundingConfig::MODE_ROUND),
            $this->extractMode($energyConfig, ['available', 'storage'], BalanceRoundingConfig::MODE_FLOOR),
        );

        $tickDuration = $config['tick_duration_seconds'] ?? $config['tick_duration'] ?? 3600;

        return new BalanceConfig(
            isset($config['minimum_speed_modifier']) ? (float) $config['minimum_speed_modifier'] : 0.01,
            isset($config['maximum_discount']) ? (float) $config['maximum_discount'] : 0.95,
            (int) $tickDuration,
            $rounding,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFile(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Balance configuration file "%s" not found.', $path));
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            $data = require $path;
        } elseif ($extension === 'yml' || $extension === 'yaml') {
            if (!function_exists('yaml_parse_file')) {
                throw new RuntimeException('YAML extension is required to parse balance configuration.');
            }

            $data = yaml_parse_file($path);
        } else {
            throw new InvalidArgumentException(sprintf('Unsupported balance configuration format "%s".', $extension));
        }

        if (!is_array($data)) {
            throw new RuntimeException('Balance configuration must return an array.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $config
     * @param string|string[]      $keys
     */
    private function extractMode(array $config, string|array $keys, string $default): string
    {
        $keys = (array) $keys;

        foreach ($keys as $key) {
            if (isset($config[$key]) && is_string($config[$key])) {
                return strtolower($config[$key]);
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function extractEnergyMode(array $config, string $default): string
    {
        if (isset($config['stats'])) {
            $value = $config['stats'];
        } elseif (isset($config['production'])) {
            $value = $config['production'];
        } elseif (isset($config['consumption'])) {
            $value = $config['consumption'];
        } elseif (isset($config['balance'])) {
            $value = $config['balance'];
        } else {
            $value = $default;
        }

        if (is_string($value)) {
            return strtolower($value);
        }

        return $default;
    }
}
