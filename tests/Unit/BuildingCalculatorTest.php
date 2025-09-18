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
}
