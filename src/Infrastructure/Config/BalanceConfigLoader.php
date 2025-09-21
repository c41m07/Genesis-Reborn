<?php

namespace App\Infrastructure\Config;

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
