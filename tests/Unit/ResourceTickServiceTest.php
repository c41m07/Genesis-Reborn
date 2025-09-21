<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\ResourceTickService;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ResourceTickServiceTest extends TestCase
{
    public function testTickUpdatesResourcesForMultiplePlanets(): void
    {
        $service = new ResourceTickService();

        $effects = [
            'metal_mine' => [
                'produces' => [
                    'metal' => ['base' => 30, 'growth' => 1.15],
                ],
                'energy' => [
                    'consumption' => ['base' => 10, 'growth' => 1.08, 'linear' => true],
                ],
            ],
            'crystal_mine' => [
                'produces' => [
                    'crystal' => ['base' => 20, 'growth' => 1.14],
                ],
                'energy' => [
                    'consumption' => ['base' => 12, 'growth' => 1.08, 'linear' => true],
                ],
            ],
            'solar_plant' => [
                'energy' => [
                    'production' => ['base' => 40, 'growth' => 1.13],
                ],
            ],
            'fusion_reactor' => [
                'energy' => [
                    'production' => ['base' => 320, 'growth' => 1.18],
                ],
                'consumes' => [
                    'hydrogen' => ['base' => 30, 'growth' => 1.16],
                ],
            ],
            'antimatter_reactor' => [
                'energy' => [
                    'production' => ['base' => 800, 'growth' => 1.2],
                ],
                'consumes' => [
                    'hydrogen' => ['base' => 60, 'growth' => 1.2],
                ],
            ],
            'storage_depot' => [
                'storage' => [
                    'metal' => ['base' => 5000, 'growth' => 1.5],
                    'crystal' => ['base' => 4000, 'growth' => 1.4],
                    'hydrogen' => ['base' => 3000, 'growth' => 1.4],
                    'energy' => ['base' => 25, 'growth' => 1.2],
                ],
            ],
        ];

        $lastTick = new DateTimeImmutable('2025-09-20 10:00:00');
        $now = $lastTick->add(new DateInterval('PT2H'));

        $planetStates = [
            1 => [
                'planet_id' => 1,
                'player_id' => 10,
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
                'planet_id' => 2,
                'player_id' => 20,
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

        $result = $service->tick($planetStates, $now, $effects);

        self::assertCount(2, $result);
        $planetOne = $result[1];
        $planetTwo = $result[2];

        $rawMetalPerHour = 30 * pow(1.15, 4);
        $rawCrystalPerHour = 20 * pow(1.14, 2);
        $energyProduction = (40 * pow(1.13, 3)) + (320 * pow(1.18, 1)) + (800 * pow(1.2, 0));
        $energyConsumption = (10 * pow(1.08, 4) * 5) + (12 * pow(1.08, 2) * 3);
        $energyRatio = max(0.0, min(1.0, $energyProduction / $energyConsumption));
        $expectedMetalPerHour = $rawMetalPerHour * $energyRatio;
        $expectedCrystalPerHour = $rawCrystalPerHour * $energyRatio;
        $hydrogenConsumptionPerHour = (30 * pow(1.16, 1) + 60 * pow(1.2, 0)) * $energyRatio;

        $expectedMetalAfter = (int) floor(1000 + $expectedMetalPerHour * 2 + 0.000001);
        $expectedCrystalAfter = (int) floor(500 + $expectedCrystalPerHour * 2 + 0.000001);
        $expectedHydrogenAfter = (int) floor(200 - $hydrogenConsumptionPerHour * 2 + 0.000001);

        self::assertEqualsWithDelta($expectedMetalAfter, $planetOne['resources']['metal'], 1.0);
        self::assertEqualsWithDelta($expectedCrystalAfter, $planetOne['resources']['crystal'], 1.0);
        self::assertEqualsWithDelta($expectedHydrogenAfter, $planetOne['resources']['hydrogen'], 1.0);
        self::assertEqualsWithDelta($energyProduction, $planetOne['energy']['production'], 0.5);
        self::assertSame(17500, $planetOne['capacities']['metal']);
        self::assertSame(130, $planetOne['capacities']['energy']);
        self::assertSame(130, $planetOne['resources']['energy']);
        self::assertSame(2 * 3600, $planetOne['elapsed_seconds']);
        self::assertEqualsWithDelta($energyRatio, $planetOne['energy']['ratio'], 0.0001);
        self::assertSame(130, $planetOne['energy']['available']);

        self::assertSame($planetStates[2]['resources']['metal'], $planetTwo['resources']['metal']);
        self::assertSame($planetStates[2]['resources']['crystal'], $planetTwo['resources']['crystal']);
        self::assertGreaterThan($planetStates[2]['capacities']['metal'], $planetTwo['capacities']['metal']);
        self::assertSame(0, $planetTwo['energy']['production']);
        self::assertSame(0, $planetTwo['energy']['available']);
    }

    public function testStorageCapacityRemainsStableAcrossSequentialTicks(): void
    {
        $effects = [
            'storage_depot' => [
                'storage' => [
                    'metal' => ['base' => 5000, 'growth' => 1.5],
                    'crystal' => ['base' => 2500, 'growth' => 1.4],
                    'hydrogen' => ['base' => 2000, 'growth' => 1.3],
                    'energy' => ['base' => 50, 'growth' => 1.1],
                ],
            ],
        ];

        $service = new ResourceTickService($effects);

        $startTick = new DateTimeImmutable('2025-09-20 08:00:00');
        $firstTickTime = $startTick->add(new DateInterval('PT1H'));

        $initialState = [
            'planet_id' => 1,
            'player_id' => 10,
            'resources' => [
                'metal' => 2000,
                'crystal' => 1000,
                'hydrogen' => 500,
                'energy' => 0,
            ],
            'capacities' => [
                'metal' => 10000,
                'crystal' => 8000,
                'hydrogen' => 6000,
                'energy' => 100,
            ],
            'base_capacities' => [
                'metal' => 10000,
                'crystal' => 8000,
                'hydrogen' => 6000,
                'energy' => 100,
            ],
            'last_tick' => $startTick,
            'building_levels' => [
                'storage_depot' => 2,
            ],
        ];

        $firstTickResult = $service->tick([1 => $initialState], $firstTickTime);
        $firstPlanetTick = $firstTickResult[1];

        self::assertSame(17500, $firstPlanetTick['capacities']['metal']);

        $secondState = $initialState;
        $secondState['last_tick'] = $firstTickTime;
        $secondState['resources'] = $firstPlanetTick['resources'];
        $secondState['capacities'] = $firstPlanetTick['capacities'];

        $secondTickTime = $firstTickTime->add(new DateInterval('PT1H'));
        $secondTickResult = $service->tick([1 => $secondState], $secondTickTime);
        $secondPlanetTick = $secondTickResult[1];

        self::assertSame($firstPlanetTick['capacities']['metal'], $secondPlanetTick['capacities']['metal']);
        self::assertSame($firstPlanetTick['capacities']['crystal'], $secondPlanetTick['capacities']['crystal']);
        self::assertSame($firstPlanetTick['capacities']['hydrogen'], $secondPlanetTick['capacities']['hydrogen']);
        self::assertSame($firstPlanetTick['capacities']['energy'], $secondPlanetTick['capacities']['energy']);
    }

    public function testStorageCapacityDoesNotAccumulateWithoutExplicitBase(): void
    {
        $effects = [
            'storage_depot' => [
                'storage' => [
                    'metal' => ['base' => 5000, 'growth' => 1.5],
                    'crystal' => ['base' => 2500, 'growth' => 1.4],
                    'hydrogen' => ['base' => 2000, 'growth' => 1.3],
                    'energy' => ['base' => 50, 'growth' => 1.1],
                ],
            ],
        ];

        $service = new ResourceTickService($effects);

        $startTick = new DateTimeImmutable('2025-09-20 08:00:00');
        $firstTickTime = $startTick->add(new DateInterval('PT1H'));

        $initialCapacities = [
            'metal' => 17500,
            'crystal' => 11500,
            'hydrogen' => 8600,
            'energy' => 155,
        ];

        $initialState = [
            'planet_id' => 1,
            'player_id' => 10,
            'resources' => [
                'metal' => 2000,
                'crystal' => 1000,
                'hydrogen' => 500,
                'energy' => 0,
            ],
            'capacities' => $initialCapacities,
            'last_tick' => $startTick,
            'building_levels' => [
                'storage_depot' => 2,
            ],
            'previous_building_levels' => [
                'storage_depot' => 2,
            ],
        ];

        $firstTick = $service->tick([1 => $initialState], $firstTickTime);
        $firstPlanetTick = $firstTick[1];

        self::assertSame($initialCapacities['metal'], $firstPlanetTick['capacities']['metal']);
        self::assertSame($initialCapacities['crystal'], $firstPlanetTick['capacities']['crystal']);
        self::assertSame($initialCapacities['hydrogen'], $firstPlanetTick['capacities']['hydrogen']);
        self::assertSame($initialCapacities['energy'], $firstPlanetTick['capacities']['energy']);

        $secondState = $initialState;
        $secondState['last_tick'] = $firstTickTime;
        $secondState['resources'] = $firstPlanetTick['resources'];
        $secondState['capacities'] = $firstPlanetTick['capacities'];
        $secondState['previous_building_levels'] = $initialState['building_levels'];

        $secondTickTime = $firstTickTime->add(new DateInterval('PT1H'));
        $secondTick = $service->tick([1 => $secondState], $secondTickTime);
        $secondPlanetTick = $secondTick[1];

        self::assertSame($firstPlanetTick['capacities']['metal'], $secondPlanetTick['capacities']['metal']);
        self::assertSame($firstPlanetTick['capacities']['crystal'], $secondPlanetTick['capacities']['crystal']);
        self::assertSame($firstPlanetTick['capacities']['hydrogen'], $secondPlanetTick['capacities']['hydrogen']);
        self::assertSame($firstPlanetTick['capacities']['energy'], $secondPlanetTick['capacities']['energy']);

        $upgradeState = $secondState;
        $upgradeState['last_tick'] = $secondTickTime;
        $upgradeState['resources'] = $secondPlanetTick['resources'];
        $upgradeState['capacities'] = $secondPlanetTick['capacities'];
        $upgradeState['previous_building_levels'] = $secondState['building_levels'];
        $upgradeState['building_levels'] = [
            'storage_depot' => 3,
        ];

        $upgradeTickTime = $secondTickTime->add(new DateInterval('PT1H'));
        $upgradeTick = $service->tick([1 => $upgradeState], $upgradeTickTime);
        $upgradePlanetTick = $upgradeTick[1];

        self::assertSame(21250, $upgradePlanetTick['capacities']['metal']);
        self::assertSame(12900, $upgradePlanetTick['capacities']['crystal']);
        self::assertSame(9380, $upgradePlanetTick['capacities']['hydrogen']);
        self::assertSame(161, $upgradePlanetTick['capacities']['energy']);
    }
}
