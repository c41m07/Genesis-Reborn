<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\CostService;
use App\Domain\ValueObject\ResourceCost;
use App\Infrastructure\Config\BalanceConfigLoader;
use PHPUnit\Framework\TestCase;

class CostServiceTest extends TestCase
{
    private CostService $service;

    private BalanceConfigLoader $loader;

    public function testMetalMineNextLevelCostMatchesLegacyValues(): void
    {
        $metalMine = $this->loader->getBuildingConfig('metal_mine');

        $baseCost = ResourceCost::fromArray($metalMine->getBaseCost());

        $level5Cost = $this->service->nextLevelCost($baseCost, (float)$metalMine->getGrowthCost(), 5);
        $level9Cost = $this->service->nextLevelCost($metalMine->getBaseCost(), (float)$metalMine->getGrowthCost(), 9);

        self::assertInstanceOf(ResourceCost::class, $level5Cost);
        self::assertSame(['metal' => 629, 'crystal' => 157], $level5Cost->toArray());
        self::assertSame(['metal' => 4123, 'crystal' => 1031], $level9Cost);
    }

    public function testPropulsionResearchCumulativeCostFromSnapshots(): void
    {
        $technology = $this->loader->getTechnologyConfig('propulsion_basic');

        $cumulative = $this->service->cumulativeCost(
            ResourceCost::fromArray($technology->getBaseCost()),
            (float)$technology->getGrowthCost(),
            0,
            3
        );

        self::assertInstanceOf(ResourceCost::class, $cumulative);
        self::assertSame([
            'metal' => 645,
            'crystal' => 430,
            'hydrogen' => 215,
        ], $cumulative->toArray());
    }

    public function testScaledDurationAndDiscountMatchReferenceCalculations(): void
    {
        $researchLab = $this->loader->getBuildingConfig('research_lab');
        $shipyard = $this->loader->getBuildingConfig('shipyard');

        $duration = $this->service->scaledDuration($researchLab->getBaseTime(), (float)$researchLab->getGrowthTime(), 5, 1.35);
        self::assertSame(311, $duration);

        $discounted = $this->service->applyDiscount(ResourceCost::fromArray($shipyard->getBaseCost()), 0.15);
        self::assertInstanceOf(ResourceCost::class, $discounted);
        self::assertSame(['metal' => 357, 'crystal' => 221, 'hydrogen' => 102], $discounted->toArray());
    }

    protected function setUp(): void
    {
        $basePath = dirname(__DIR__, 2) . '/config/balance';
        $this->loader = new BalanceConfigLoader($basePath);
        $this->service = new CostService($this->loader->getBalanceConfig());
    }
}
