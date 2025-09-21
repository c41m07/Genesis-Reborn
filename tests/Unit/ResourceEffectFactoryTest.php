<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\ResourceEffectFactory;
use PHPUnit\Framework\TestCase;

class ResourceEffectFactoryTest extends TestCase
{
    public function testFactoryProducesEffectsFromYamlLikeConfig(): void
    {
        $config = [
            'metal_mine' => [
                'affects' => 'metal',
                'prod_base' => '100',
                'prod_growth' => '1.15',
                'energy_use_base' => '10',
                'energy_use_growth' => '1.1',
                'energy_use_linear' => true,
                'storage' => [
                    'metal' => ['base' => '1000', 'growth' => '1.5'],
                ],
                'upkeep' => [
                    'hydrogen' => ['base' => '5', 'growth' => '1.02', 'linear' => true],
                ],
            ],
            'solar_plant' => [
                'affects' => 'energy',
                'prod_base' => '50',
                'prod_growth' => '1.12',
                'energy_use_base' => '0',
                'energy_use_growth' => '1',
            ],
        ];

        $effects = ResourceEffectFactory::fromBuildingConfig($config);

        self::assertArrayHasKey('metal_mine', $effects);
        self::assertSame(100.0, $effects['metal_mine']['produces']['metal']['base']);
        self::assertSame(1.15, $effects['metal_mine']['produces']['metal']['growth']);
        self::assertSame(10.0, $effects['metal_mine']['energy']['consumption']['base']);
        self::assertSame(1.1, $effects['metal_mine']['energy']['consumption']['growth']);
        self::assertTrue($effects['metal_mine']['energy']['consumption']['linear']);
        self::assertSame(1000.0, $effects['metal_mine']['storage']['metal']['base']);
        self::assertSame(1.5, $effects['metal_mine']['storage']['metal']['growth']);
        self::assertSame(5.0, $effects['metal_mine']['consumes']['hydrogen']['base']);
        self::assertSame(1.02, $effects['metal_mine']['consumes']['hydrogen']['growth']);
        self::assertTrue($effects['metal_mine']['consumes']['hydrogen']['linear']);

        self::assertArrayHasKey('solar_plant', $effects);
        self::assertSame(50.0, $effects['solar_plant']['energy']['production']['base']);
        self::assertSame(1.12, $effects['solar_plant']['energy']['production']['growth']);
    }
}
