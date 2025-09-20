<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Entity\BuildingDefinition;
use App\Domain\Service\BuildingCalculator;
use PHPUnit\Framework\TestCase;

class BuildingCalculatorTest extends TestCase
{
    private BuildingCalculator $calculator;
    private BuildingDefinition $definition;

    protected function setUp(): void
    {
        $this->calculator = new BuildingCalculator();
        $this->definition = new BuildingDefinition(
            'metal_mine',
            'Mine de métal',
            ['metal' => 60, 'crystal' => 15],
            1.6,
            20,
            1.5,
            100,
            1.1,
            10,
            1.05,
            true,
            'metal'
        );
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
            ['hydrogen' => ['base' => 30, 'growth' => 1.16]]
        );

        $levelOne = $this->calculator->upkeepAt($definition, 1);
        $levelTwo = $this->calculator->upkeepAt($definition, 2);

        self::assertSame(30, $levelOne['hydrogen'] ?? 0);
        self::assertGreaterThan($levelOne['hydrogen'], $levelTwo['hydrogen']);
    }

    public function testAntimatterReactorUpkeepAndProduction(): void
    {
        $definition = new BuildingDefinition(
            'antimatter_reactor',
            'Réacteur à antimatière',
            ['metal' => 3200, 'crystal' => 2200, 'hydrogen' => 1200],
            1.55,
            90,
            1.6,
            800,
            1.2,
            0,
            1.0,
            false,
            'energy',
            [],
            null,
            [],
            ['hydrogen' => ['base' => 60, 'growth' => 1.2]]
        );

        $levelOneProduction = $this->calculator->productionAt($definition, 1);
        $levelTwoProduction = $this->calculator->productionAt($definition, 2);
        $levelOneUpkeep = $this->calculator->upkeepAt($definition, 1);
        $levelTwoUpkeep = $this->calculator->upkeepAt($definition, 2);

        self::assertSame(800, $levelOneProduction);
        self::assertGreaterThan($levelOneProduction, $levelTwoProduction);
        self::assertSame(60, $levelOneUpkeep['hydrogen'] ?? 0);
        self::assertGreaterThan($levelOneUpkeep['hydrogen'] ?? 0, $levelTwoUpkeep['hydrogen'] ?? 0);
    }
}
