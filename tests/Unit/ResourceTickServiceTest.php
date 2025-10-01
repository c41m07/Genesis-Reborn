<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\ResourceEffectFactory;
use App\Domain\Service\ResourceTickService;
use App\Infrastructure\Config\BalanceConfigLoader;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ResourceTickServiceTest extends TestCase
{
    private ResourceTickService $service;

    public function testTickUpdatesResourcesForMultiplePlanets(): void
    {
        $lastTick = new DateTimeImmutable('2025-09-20 10:00:00');
        $now = $lastTick->add(new DateInterval('PT2H'));

        $planetStates = [
            1 => [
                'resources' => [
                    'metal' => 1000,
                    'crystal' => 500,
                    'hydrogen' => 200,
                    'energy' => 0,
                ],
                'capacities' => [
                    'metal' => 10000,
                    'crystal' => 8000,
                    'hydrogen' => 5000,
                    'energy' => 100,
                ],
                'base_capacities' => [
                    'metal' => 10000,
                    'crystal' => 8000,
                    'hydrogen' => 5000,
                    'energy' => 100,
                ],
                'last_tick' => $lastTick,
                'building_levels' => [
                    'metal_mine' => 5,
                    'crystal_mine' => 3,
                    'solar_plant' => 4,
                    'fusion_reactor' => 2,
                    'antimatter_reactor' => 1,
                    'storage_depot' => 2,
                ],
            ],
            2 => [
                'resources' => [
                    'metal' => 5000,
                    'crystal' => 2500,
                    'hydrogen' => 1500,
                    'energy' => 0,
                ],
                'capacities' => [
                    'metal' => 8000,
                    'crystal' => 6000,
                    'hydrogen' => 4000,
                    'energy' => 50,
                ],
                'base_capacities' => [
                    'metal' => 8000,
                    'crystal' => 6000,
                    'hydrogen' => 4000,
                    'energy' => 50,
                ],
                'last_tick' => $lastTick,
                'building_levels' => [
                    'metal_mine' => 3,
                    'crystal_mine' => 2,
                    'storage_depot' => 1,
                ],
            ],
        ];

        $result = $this->service->tick($planetStates, $now);

        self::assertCount(2, $result);
        $planetOne = $result[1];
        self::assertSame([
            'metal' => 1699,
            'crystal' => 764,
            'hydrogen' => 10,
            'energy' => 1487,
        ], $planetOne['resources']);
        self::assertSame([
            'metal' => 350,
            'crystal' => 132,
            'hydrogen' => -95,
            'energy' => 744,
        ], $planetOne['production_per_hour']);
        self::assertSame([
            'metal' => 90000,
            'crystal' => 72000,
            'hydrogen' => 53000,
            'energy' => 3850,
        ], $planetOne['capacities']);
        self::assertSame([
            'production' => 776,
            'consumption' => 33,
            'balance' => 744,
            'available' => 1487,
            'ratio' => 1.0,
        ], $planetOne['energy']);
        self::assertSame(7200, $planetOne['elapsed_seconds']);

        $planetTwo = $result[2];
        self::assertSame([
            'metal' => 5000,
            'crystal' => 2500,
            'hydrogen' => 1500,
            'energy' => 0,
        ], $planetTwo['resources']);
        self::assertSame([
            'metal' => 0,
            'crystal' => 0,
            'energy' => -29,
        ], $planetTwo['production_per_hour']);
        self::assertSame([
            'metal' => 58000,
            'crystal' => 46000,
            'hydrogen' => 34000,
            'energy' => 2550,
        ], $planetTwo['capacities']);
        self::assertSame([
            'production' => 0,
            'consumption' => 29,
            'balance' => -29,
            'available' => 0,
            'ratio' => 0.0,
        ], $planetTwo['energy']);
        self::assertSame(7200, $planetTwo['elapsed_seconds']);
    }

    public function testStorageCapacityRemainsStableAcrossSequentialTicks(): void
    {
        $startTick = new DateTimeImmutable('2025-09-20 08:00:00');
        $firstTickTime = $startTick->add(new DateInterval('PT1H'));

        $initialState = [
            'resources' => ['metal' => 2000, 'crystal' => 1000, 'hydrogen' => 500, 'energy' => 0],
            'capacities' => ['metal' => 10000, 'crystal' => 8000, 'hydrogen' => 6000, 'energy' => 100],
            'base_capacities' => ['metal' => 10000, 'crystal' => 8000, 'hydrogen' => 6000, 'energy' => 100],
            'last_tick' => $startTick,
            'building_levels' => ['storage_depot' => 2],
        ];

        $firstTick = $this->service->tick([1 => $initialState], $firstTickTime)[1];

        self::assertSame([
            'metal' => 90000,
            'crystal' => 72000,
            'hydrogen' => 54000,
            'energy' => 3850,
        ], $firstTick['capacities']);

        $secondState = $initialState;
        $secondState['resources'] = $firstTick['resources'];
        $secondState['capacities'] = $firstTick['capacities'];
        $secondState['last_tick'] = $firstTickTime;

        $secondTickTime = $firstTickTime->add(new DateInterval('PT1H'));
        $secondTick = $this->service->tick([1 => $secondState], $secondTickTime)[1];

        self::assertSame($firstTick['capacities'], $secondTick['capacities']);
    }

    public function testStorageCapacityDoesNotAccumulateWithoutExplicitBase(): void
    {
        $startTick = new DateTimeImmutable('2025-09-20 08:00:00');
        $firstTickTime = $startTick->add(new DateInterval('PT1H'));

        $initialCapacities = [
            'metal' => 90000,
            'crystal' => 72000,
            'hydrogen' => 54000,
            'energy' => 3850,
        ];

        $initialState = [
            'resources' => ['metal' => 2000, 'crystal' => 1000, 'hydrogen' => 500, 'energy' => 0],
            'capacities' => $initialCapacities,
            'last_tick' => $startTick,
            'building_levels' => ['storage_depot' => 2],
            'previous_building_levels' => ['storage_depot' => 2],
        ];

        $firstTick = $this->service->tick([1 => $initialState], $firstTickTime)[1];

        self::assertSame($initialCapacities, $firstTick['capacities']);

        $secondState = $initialState;
        $secondState['resources'] = $firstTick['resources'];
        $secondState['capacities'] = $firstTick['capacities'];
        $secondState['last_tick'] = $firstTickTime;

        $secondTickTime = $firstTickTime->add(new DateInterval('PT1H'));
        $secondTick = $this->service->tick([1 => $secondState], $secondTickTime)[1];

        self::assertSame($firstTick['capacities'], $secondTick['capacities']);

        $upgradeState = $secondState;
        $upgradeState['resources'] = $secondTick['resources'];
        $upgradeState['capacities'] = $secondTick['capacities'];
        $upgradeState['last_tick'] = $secondTickTime;
        $upgradeState['previous_building_levels'] = $secondState['building_levels'];
        $upgradeState['building_levels'] = ['storage_depot' => 3];

        $upgradeTickTime = $secondTickTime->add(new DateInterval('PT1H'));
        $upgradeTick = $this->service->tick([1 => $upgradeState], $upgradeTickTime)[1];

        self::assertSame([
            'metal' => 138000,
            'crystal' => 110400,
            'hydrogen' => 82800,
            'energy' => 5725,
        ], $upgradeTick['capacities']);
    }

    protected function setUp(): void
    {
        $basePath = dirname(__DIR__, 2) . '/config/balance';
        $loader = new BalanceConfigLoader($basePath);
        $effects = ResourceEffectFactory::fromBuildingConfig($loader->getBuildingConfigs());

        $this->service = new ResourceTickService($effects, $loader->getBalanceConfig());
    }

    public function testEnergyDeficitCoveredByStockMaintainsProductionUntilDepleted(): void
    {
        $startTick = new DateTimeImmutable('2025-09-20 08:00:00');
        $firstTickTime = $startTick->add(new DateInterval('PT1M'));

        $effectsOverride = [
            'test_mine' => [
                'produces' => [
                    'metal' => ['base' => 120.0, 'growth' => 1.0],
                ],
                'consumes' => [
                    'energy' => ['base' => 600.0, 'growth' => 1.0],
                ],
            ],
        ];

        $initialState = [
            'resources' => ['metal' => 0, 'crystal' => 0, 'hydrogen' => 0, 'energy' => 20],
            'capacities' => ['metal' => 1000, 'crystal' => 1000, 'hydrogen' => 1000, 'energy' => 100],
            'last_tick' => $startTick,
            'building_levels' => ['test_mine' => 1],
        ];

        $firstTick = $this->service->tick([1 => $initialState], $firstTickTime, $effectsOverride)[1];

        self::assertSame(1.0, $firstTick['energy']['ratio']);
        self::assertSame(2, $firstTick['resources']['metal']);
        self::assertSame(0, $firstTick['resources']['crystal']);
        self::assertSame(0, $firstTick['resources']['hydrogen']);
        self::assertSame(10, $firstTick['energy']['available']);
        self::assertSame(120, $firstTick['production_per_hour']['metal']);

        $secondState = $initialState;
        $secondState['resources'] = $firstTick['resources'];
        $secondState['capacities'] = $firstTick['capacities'];
        $secondState['last_tick'] = $firstTickTime;

        $secondTickTime = $firstTickTime->add(new DateInterval('PT1M'));
        $secondTick = $this->service->tick([1 => $secondState], $secondTickTime, $effectsOverride)[1];

        self::assertSame(0.0, $secondTick['energy']['ratio']);
        self::assertSame(2, $secondTick['resources']['metal']);
        self::assertSame(0, $secondTick['energy']['available']);
        self::assertSame(0, $secondTick['production_per_hour']['metal']);
    }
}
