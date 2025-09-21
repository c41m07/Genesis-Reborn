<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\Config\BalanceConfigLoader;
use PHPUnit\Framework\TestCase;

class BalanceConfigLoaderTest extends TestCase
{
    public function testFromArrayBuildsBalanceConfig(): void
    {
        $loader = new BalanceConfigLoader();

        $config = $loader->fromArray([
            'minimum_speed_modifier' => 0.05,
            'maximum_discount' => 0.5,
            'tick_duration_seconds' => 120,
            'rounding_tolerance' => 0.0005,
            'rounding' => [
                'resources' => 'ceil',
                'capacities' => 'floor',
                'production' => 'round',
                'energy' => [
                    'stats' => 'ceil',
                    'available' => 'round',
                ],
            ],
        ]);

        self::assertSame(0.05, $config->getMinimumSpeedModifier());
        self::assertSame(0.5, $config->getMaximumDiscount());
        self::assertSame(120, $config->getTickDurationSeconds());
        self::assertSame(0.0005, $config->getRoundingTolerance());
        self::assertSame(11, $config->roundResourceQuantity(10.6));
        self::assertSame(9, $config->roundCapacity(9.4));
        self::assertSame(11, $config->roundEnergyStat(10.4));
        self::assertSame(10, $config->roundEnergyAvailable(9.6));
    }

    public function testFromArrayAppliesDefaultsWhenConfigMissing(): void
    {
        $loader = new BalanceConfigLoader();

        $config = $loader->fromArray([]);

        self::assertSame(0.01, $config->getMinimumSpeedModifier());
        self::assertSame(0.95, $config->getMaximumDiscount());
        self::assertSame(3600, $config->getTickDurationSeconds());
        self::assertSame(0.000001, $config->getRoundingTolerance());
        self::assertSame(10, $config->roundResourceQuantity(10.2));
        self::assertSame(10, $config->roundCapacity(9.6));
        self::assertSame(9, $config->roundEnergyAvailable(9.6));
    }

    public function testInvalidRoundingModeThrowsException(): void
    {
        $loader = new BalanceConfigLoader();

        $this->expectException(\InvalidArgumentException::class);
        $loader->fromArray(['rounding' => ['resources' => 'invalid']]);
    }
}
