<?php

namespace App\Infrastructure\Config;

use App\Domain\Config\BalanceConfig;
use App\Domain\Config\BalanceRoundingConfig;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class BalanceConfigLoader
{
    private string $configDir;

    private ?BalanceGlobals $globals = null;

    /** @var array<string, BuildingConfig>|null */
    private ?array $buildingConfigs = null;

    /** @var array<string, ShipConfig>|null */
    private ?array $shipConfigs = null;

    /** @var array<string, TechnologyConfig>|null */
    private ?array $technologyConfigs = null;

    /** @var array<string, mixed>|null */
    private ?array $balanceCache = null;

    public function __construct(?string $configDir = null)
    {
        $defaultDir = dirname(__DIR__, 2) . '/../config/balance';
        $this->configDir = rtrim($configDir ?? $defaultDir, '/\\');
    }

    public function getGlobals(): BalanceGlobals
    {
        if ($this->globals === null) {
            $data = $this->parseFile('globals.yml');
            $initialResources = [];
            $initialCapacities = [];

            if (isset($data['initial_resources']) && is_array($data['initial_resources'])) {
                $initialResources = $data['initial_resources'];
            }

            if (isset($data['initial_capacities']) && is_array($data['initial_capacities'])) {
                $initialCapacities = $data['initial_capacities'];
            }

            $this->globals = new BalanceGlobals($initialResources, $initialCapacities);
        }

        return $this->globals;
    }

    /**
     * @return BuildingConfig[]
     */
    public function getBuildingConfigs(): array
    {
        $this->loadBuildings();

        return array_values($this->buildingConfigs);
    }

    public function getBuildingConfig(string $key): BuildingConfig
    {
        $this->loadBuildings();

        if (!isset($this->buildingConfigs[$key])) {
            throw new RuntimeException(sprintf('Unknown building configuration "%s".', $key));
        }

        return $this->buildingConfigs[$key];
    }

    /**
     * @return ShipConfig[]
     */
    public function getShipConfigs(): array
    {
        $this->loadShips();

        return array_values($this->shipConfigs);
    }

    public function getShipConfig(string $key): ShipConfig
    {
        $this->loadShips();

        if (!isset($this->shipConfigs[$key])) {
            throw new RuntimeException(sprintf('Unknown ship configuration "%s".', $key));
        }

        return $this->shipConfigs[$key];
    }

    /**
     * @return TechnologyConfig[]
     */
    public function getTechnologyConfigs(): array
    {
        $this->loadTechnologies();

        return array_values($this->technologyConfigs);
    }

    public function getTechnologyConfig(string $key): TechnologyConfig
    {
        $this->loadTechnologies();

        if (!isset($this->technologyConfigs[$key])) {
            throw new RuntimeException(sprintf('Unknown technology configuration "%s".', $key));
        }

        return $this->technologyConfigs[$key];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->balanceCache === null) {
            $this->balanceCache = $this->parseFile('balance.yml');
        }

        return $this->balanceCache;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $value = $this->all();

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function reset(): void
    {
        $this->balanceCache = null;
    }

    public function getBalanceConfig(): BalanceConfig
    {
        return $this->createBalanceConfig($this->all());
    }

    /**
     * @param array<string, mixed> $config
     */
    public function createBalanceConfig(array $config): BalanceConfig
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

    private function loadBuildings(): void
    {
        if ($this->buildingConfigs !== null) {
            return;
        }

        $data = $this->parseFile('buildings.yml');
        $configs = [];

        foreach ($data as $key => $config) {
            if (!is_string($key) || !is_array($config)) {
                throw new RuntimeException('Invalid building configuration entry encountered.');
            }

            $configs[$key] = new BuildingConfig($key, $config);
        }

        $this->buildingConfigs = $configs;
    }

    private function loadShips(): void
    {
        if ($this->shipConfigs !== null) {
            return;
        }

        $data = $this->parseFile('ships.yml');
        $configs = [];

        foreach ($data as $key => $config) {
            if (!is_string($key) || !is_array($config)) {
                throw new RuntimeException('Invalid ship configuration entry encountered.');
            }

            $configs[$key] = new ShipConfig($key, $config);
        }

        $this->shipConfigs = $configs;
    }

    private function loadTechnologies(): void
    {
        if ($this->technologyConfigs !== null) {
            return;
        }

        $data = $this->parseFile('technologies.yml');
        $configs = [];

        foreach ($data as $key => $config) {
            if (!is_string($key) || !is_array($config)) {
                throw new RuntimeException('Invalid technology configuration entry encountered.');
            }

            $configs[$key] = new TechnologyConfig($key, $config);
        }

        $this->technologyConfigs = $configs;
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

    /**
     * @return array<string, mixed>
     */
    private function parseFile(string $filename): array
    {
        $path = $this->configDir . '/' . ltrim($filename, '/');

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Balance configuration file "%s" not found.', $path));
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (ParseException $exception) {
            throw new RuntimeException(sprintf('Unable to parse balance configuration file "%s".', $path), 0, $exception);
        }

        if (!is_array($parsed)) {
            throw new RuntimeException(sprintf('Balance configuration file "%s" must contain an array structure.', $path));
        }

        return $parsed;
    }
}
