<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\CostService;
use App\Infrastructure\Config\BalanceConfigLoader;
use PHPUnit\Framework\TestCase;

class CostServiceTest extends TestCase
{
    private CostService $service;

    private BalanceConfigLoader $loader;

    protected function setUp(): void
    {
        $this->service = new CostService();
        $basePath = dirname(__DIR__, 2) . '/config/balance';
        $this->loader = new BalanceConfigLoader($basePath);
    }

    public function testMetalMineNextLevelCostMatchesLegacyValues(): void
    {
        $buildings = $this->loader->loadBuildings();
        $metalMine = $buildings['metal_mine'];

        $level5Cost = $this->service->nextLevelCost($metalMine['base_cost'], (float) $metalMine['growth_cost'], 5);
        $level9Cost = $this->service->nextLevelCost($metalMine['base_cost'], (float) $metalMine['growth_cost'], 9);

        self::assertSame(['metal' => 629, 'crystal' => 157], $level5Cost);
        self::assertSame(['metal' => 4123, 'crystal' => 1031], $level9Cost);
    }

    public function testPropulsionResearchCumulativeCostFromSnapshots(): void
    {
        $research = $this->loader->loadResearch();
        $tech = $research['propulsion_basic'];

        $cumulative = $this->service->cumulativeCost($tech['base_cost'], (float) $tech['growth_cost'], 0, 3);

        self::assertSame([
            'metal' => 645,
            'crystal' => 430,
            'hydrogen' => 215,
        ], $cumulative);
    }

    public function testScaledDurationAndDiscountMatchReferenceCalculations(): void
    {
        $buildings = $this->loader->loadBuildings();
        $researchLab = $buildings['research_lab'];
        $shipyard = $buildings['shipyard'];

        $duration = $this->service->scaledDuration((int) $researchLab['base_time'], (float) $researchLab['growth_time'], 5, 1.35);
        self::assertSame(311, $duration);

        $discounted = $this->service->applyDiscount($shipyard['base_cost'], 0.15);
        self::assertSame(['metal' => 357, 'crystal' => 221, 'hydrogen' => 102], $discounted);
    }
}
