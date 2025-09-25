<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Config;

use App\Domain\Config\BalanceConfig;
use App\Infrastructure\Config\BalanceConfigLoader;
use App\Infrastructure\Config\BuildingConfig;
use App\Infrastructure\Config\ShipConfig;
use App\Infrastructure\Config\TechnologyConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BalanceConfigLoaderTest extends TestCase
{
    private BalanceConfigLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new BalanceConfigLoader(__DIR__ . '/../../../../config/balance');
    }

    public function testGetBuildingConfigReturnsDefinition(): void
    {
        $definition = $this->loader->getBuildingConfig('metal_mine');

        self::assertInstanceOf(BuildingConfig::class, $definition);
        self::assertSame('Mine de métal', $definition->getLabel());
        self::assertSame(60, $definition->getBaseCost()['metal']);
        self::assertSame('metal', $definition->getAffects());
    }

    public function testGetShipConfigReturnsDefinition(): void
    {
        $definition = $this->loader->getShipConfig('fighter');

        self::assertInstanceOf(ShipConfig::class, $definition);
        self::assertSame('Ailes Lyrae', $definition->getLabel());
        self::assertArrayHasKey('attaque', $definition->getStats());
    }

    public function testGetTechnologyConfigReturnsDefinition(): void
    {
        $definition = $this->loader->getTechnologyConfig('propulsion_basic');

        self::assertInstanceOf(TechnologyConfig::class, $definition);
        self::assertSame('Propulsion spatiale', $definition->getLabel());
        self::assertSame(10, $definition->getMaxLevel());
    }

    public function testGetBalanceConfigReturnsDomainObject(): void
    {
        $config = $this->loader->getBalanceConfig();

        self::assertInstanceOf(BalanceConfig::class, $config);
        self::assertSame(0.01, $config->getMinimumSpeedModifier());
        self::assertSame(0.95, $config->getMaximumDiscount());
        self::assertSame(3600, $config->getTickDurationSeconds());
    }

    public function testAllReturnsCombatConfiguration(): void
    {
        $all = $this->loader->all();

        self::assertArrayHasKey('combat', $all);
        self::assertSame(6, $all['combat']['rounds']);
    }

    public function testInvalidBasePathThrowsException(): void
    {
        $loader = new BalanceConfigLoader('/path/does/not/exist');

        $this->expectException(RuntimeException::class);
        $loader->getBuildingConfigs();
    }

    public function testGetGlobalsExposesHomeworldConfiguration(): void
    {
        $globals = $this->loader->getGlobals();

        self::assertSame(1, $globals->getHomeworldMinPosition());
        self::assertSame(16, $globals->getHomeworldMaxPosition());

        $positionEight = $globals->getHomeworldBaseStats(8);
        self::assertSame(12000, $positionEight['diameter']);
        self::assertSame(-20, $positionEight['temperature_min']);
        self::assertSame(40, $positionEight['temperature_max']);

        self::assertSame(0.1, $globals->getHomeworldVariation('diameter'));
        self::assertSame(0.1, $globals->getHomeworldVariation('temperature_min'));
        self::assertSame(0.1, $globals->getHomeworldVariation('temperature_max'));

        $fallback = $globals->getHomeworldBaseStats(99);
        self::assertSame(12000, $fallback['diameter']);
        self::assertSame(-20, $fallback['temperature_min']);
        self::assertSame(40, $fallback['temperature_max']);
    }
}
