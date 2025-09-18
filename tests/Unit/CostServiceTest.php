<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\CostService;
use PHPUnit\Framework\TestCase;

class CostServiceTest extends TestCase
{
    private CostService $service;

    protected function setUp(): void
    {
        $this->service = new CostService();
    }

    public function testNextLevelAndCumulativeCost(): void
    {
        $baseCost = ['metal' => 100, 'crystal' => 50];

        $level0Cost = $this->service->nextLevelCost($baseCost, 1.5, 0);
        $level2Cost = $this->service->nextLevelCost($baseCost, 1.5, 2);

        self::assertSame(['metal' => 100, 'crystal' => 50], $level0Cost);
        self::assertSame(['metal' => 225, 'crystal' => 113], $level2Cost);

        $cumulative = $this->service->cumulativeCost($baseCost, 1.5, 0, 3);
        self::assertSame(['metal' => 100 + 150 + 225, 'crystal' => 50 + 75 + 113], $cumulative);
    }

    public function testScaledDurationAndDiscount(): void
    {
        $duration = $this->service->scaledDuration(60, 1.6, 2, 2.0);
        self::assertSame(77, $duration);

        $discounted = $this->service->applyDiscount(['metal' => 1000, 'crystal' => 400], 0.2);
        self::assertSame(['metal' => 800, 'crystal' => 320], $discounted);
    }
}
