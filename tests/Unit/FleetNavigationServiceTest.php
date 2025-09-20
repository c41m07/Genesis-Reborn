<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\FleetNavigationService;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FleetNavigationServiceTest extends TestCase
{
    public function testPlanCalculatesDistanceEtaAndFuel(): void
    {
        $service = new FleetNavigationService();

        $origin = ['galaxy' => 1, 'system' => 20, 'position' => 5];
        $destination = ['galaxy' => 1, 'system' => 25, 'position' => 9];
        $composition = ['heavy_transport' => 10, 'fighter' => 5];
        $shipStats = [
            'heavy_transport' => ['speed' => 8000, 'fuel_per_distance' => 0.5],
            'fighter' => ['speed' => 12000, 'fuel_per_distance' => 0.4],
        ];
        $departure = new DateTimeImmutable('2025-09-20 12:00:00');

        $result = $service->plan(
            $origin,
            $destination,
            $composition,
            $shipStats,
            $departure,
            ['speed_bonus' => 0.1, 'fuel_reduction' => 0.1]
        );

        $expectedDistance = 5 * 95 + 4 * 5 + 10; // system diff + position diff + base
        $expectedSpeed = (int) round(8000 * (1 + 0.1));
        $expectedTravelSeconds = (int) ceil($expectedDistance / $expectedSpeed * 3600);
        $expectedArrival = $departure->add(new DateInterval('PT' . $expectedTravelSeconds . 'S'));
        $rawFuel = (0.5 * $expectedDistance * 10) + (0.4 * $expectedDistance * 5);
        $expectedFuel = (int) ceil($rawFuel * 0.9);

        self::assertSame($expectedDistance, $result['distance']);
        self::assertSame($expectedSpeed, $result['speed']);
        self::assertSame($expectedTravelSeconds, $result['travel_time']);
        self::assertEquals($expectedArrival, $result['arrival_time']);
        self::assertSame($expectedFuel, $result['fuel']);
    }

    public function testPlanThrowsWhenCompositionEmpty(): void
    {
        $service = new FleetNavigationService();

        $this->expectException(InvalidArgumentException::class);
        $service->plan(
            ['galaxy' => 1, 'system' => 1, 'position' => 1],
            ['galaxy' => 1, 'system' => 2, 'position' => 1],
            [],
            [],
            new DateTimeImmutable()
        );
    }
}
