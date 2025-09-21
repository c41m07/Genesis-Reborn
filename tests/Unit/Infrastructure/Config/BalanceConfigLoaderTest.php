<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Config;

use App\Infrastructure\Config\BalanceConfigLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BalanceConfigLoaderTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = dirname(__DIR__, 4) . '/config/balance';
    }

    public function testLoadsBuildingsConfigIdenticalToLegacyPhpArrays(): void
    {
        $loader = new BalanceConfigLoader($this->configPath);

        $expected = require dirname(__DIR__, 4) . '/config/game/buildings.php';
        $actual = $loader->loadBuildings();

        self::assertSame($expected, $actual);
    }

    public function testLoadsResearchConfigIdenticalToLegacyPhpArrays(): void
    {
        $loader = new BalanceConfigLoader($this->configPath);

        $expected = require dirname(__DIR__, 4) . '/config/game/research.php';
        $actual = $loader->loadResearch();

        self::assertSame($expected, $actual);
    }

    public function testLoadsShipConfigIdenticalToLegacyPhpArrays(): void
    {
        $loader = new BalanceConfigLoader($this->configPath);

        $expected = require dirname(__DIR__, 4) . '/config/game/ships.php';
        $actual = $loader->loadShips();

        self::assertSame($expected, $actual);
    }

    public function testLoadAllReturnsCompleteDataset(): void
    {
        $loader = new BalanceConfigLoader($this->configPath);

        $all = $loader->loadAll();

        self::assertCount(3, $all);
        self::assertSame($loader->loadBuildings(), $all['buildings']);
        self::assertSame($loader->loadResearch(), $all['research']);
        self::assertSame($loader->loadShips(), $all['ships']);
    }

    public function testThrowsWhenSectionUnknown(): void
    {
        $loader = new BalanceConfigLoader($this->configPath);

        $this->expectException(InvalidArgumentException::class);
        $loader->load('unknown');
    }

    public function testThrowsWhenFileMissing(): void
    {
        $loader = new BalanceConfigLoader($this->configPath, ['buildings' => 'missing.yaml']);

        $this->expectException(RuntimeException::class);
        $loader->load('buildings');
    }

    public function testThrowsWhenBasePathInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BalanceConfigLoader('/does/not/exist');
    }
}
