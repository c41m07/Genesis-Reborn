<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Entity\BuildingDefinition;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use PHPUnit\Framework\TestCase;

class BuildingCalculatorTest extends TestCase
{
    private BuildingCalculator $calculator;
    private BuildingCatalog $catalog;
    private BuildingDefinition $definition;

    protected function setUp(): void
    {
        $config = [
            'metal_mine' => [
                'label' => 'Mine de métal',
                'base_cost' => ['metal' => 60, 'crystal' => 15],
                'growth_cost' => 1.6,
                'base_time' => 20,
                'growth_time' => 1.5,
                'prod_base' => 100,
                'prod_growth' => 1.1,
                'energy_use_base' => 10,
                'energy_use_growth' => 1.05,
                'energy_use_linear' => true,
                'affects' => 'metal',
            ],
            'worker_factory' => [
                'label' => 'Complexe d’ouvriers',
                'base_cost' => ['metal' => 400, 'crystal' => 120],
                'growth_cost' => 1.6,
                'base_time' => 30,
                'growth_time' => 1.4,
                'prod_base' => 0,
                'prod_growth' => 1.0,
                'energy_use_base' => 18,
                'energy_use_growth' => 1.1,
                'energy_use_linear' => true,
                'affects' => 'infrastructure',
                'construction_speed_bonus' => ['per_level' => 0.05, 'max' => 0.5],
            ],
            'robot_factory' => [
                'label' => 'Chantier robotique',
                'base_cost' => ['metal' => 2000, 'crystal' => 800, 'hydrogen' => 200],
                'growth_cost' => 1.7,
                'base_time' => 60,
                'growth_time' => 1.5,
                'prod_base' => 0,
                'prod_growth' => 1.0,
                'energy_use_base' => 30,
                'energy_use_growth' => 1.15,
                'energy_use_linear' => true,
                'affects' => 'infrastructure',
                'construction_speed_bonus' => ['per_level' => 0.1, 'max' => 0.75],
            ],
        ];

        $this->catalog = new BuildingCatalog($config);
        $this->calculator = new BuildingCalculator($this->catalog);
        $this->definition = $this->catalog->get('metal_mine');
    }

    public function testNextCostScalesWithLevel(): void
    {
        $level0 = $this->calculator->nextCost($this->definition, 0);
        $level1 = $this->calculator->nextCost($this->definition, 1);

        self::assertSame(['metal' => 60, 'crystal' => 15], $level0);
        self::assertGreaterThan($level0['metal'], $level1['metal']);
        self::assertGreaterThan($level0['crystal'], $level1['crystal']);
    }

    public function testProductionAndEnergyUse(): void
    {
        self::assertSame(0, $this->calculator->productionAt($this->definition, 0));
        self::assertSame(100, $this->calculator->productionAt($this->definition, 1));
        self::assertGreaterThan(100, $this->calculator->productionAt($this->definition, 2));

        self::assertSame(0, $this->calculator->energyUseAt($this->definition, 0));
        self::assertSame(11, $this->calculator->energyUseAt($this->definition, 1));
    }

    public function testUpkeepScalingWithLevel(): void
    {
        $definition = new BuildingDefinition(
            'fusion_reactor',
            'Réacteur à fusion',
            ['metal' => 900, 'crystal' => 360, 'hydrogen' => 180],
            1.55,
            60,
            1.6,
            320,
            1.18,
            0,
            1.0,
            false,
            'energy',
            [],
            null,
            [],
            [],
            ['hydrogen' => ['base' => 30, 'growth' => 1.16]]
        );

        $levelOne = $this->calculator->upkeepAt($definition, 1);
        $levelTwo = $this->calculator->upkeepAt($definition, 2);

        self::assertSame(30, $levelOne['hydrogen'] ?? 0);
        self::assertGreaterThan($levelOne['hydrogen'], $levelTwo['hydrogen']);
    }

    public function testConstructionSpeedBonusIsComputed(): void
    {
        $worker = $this->catalog->get('worker_factory');

        self::assertSame(0.0, $this->calculator->constructionSpeedBonusAt($worker, 0));
        self::assertEqualsWithDelta(0.05, $this->calculator->constructionSpeedBonusAt($worker, 1), 0.0001);
        self::assertEqualsWithDelta(0.10, $this->calculator->constructionSpeedBonusAt($worker, 2), 0.0001);
    }

    public function testNextTimeAppliesConstructionSpeedReductions(): void
    {
        $baseTime = $this->calculator->nextTime($this->definition, 0);
        $reduced = $this->calculator->nextTime($this->definition, 0, [
            'worker_factory' => 4,
            'robot_factory' => 2,
        ]);

        self::assertSame(20, $baseTime);
        self::assertLessThan($baseTime, $reduced);
        self::assertGreaterThanOrEqual(1, $reduced);

    }
}
