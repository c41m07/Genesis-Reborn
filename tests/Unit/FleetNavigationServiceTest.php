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
    public function testDistanceUsesHubAndUnitConversions(): void
    {
        $service = new FleetNavigationService();

        self::assertEquals(0.25, $service->distance(['galaxy' => 1, 'system' => 1, 'position' => 6], ['galaxy' => 1, 'system' => 1, 'position' => 2]));

        $sameGalaxy = $service->distance(['galaxy' => 1, 'system' => 1, 'position' => 6], ['galaxy' => 1, 'system' => 2, 'position' => 3]);
        self::assertEqualsWithDelta(11.4375, $sameGalaxy, 1e-6);

        $crossGalaxy = $service->distance(['galaxy' => 1, 'system' => 1, 'position' => 6], ['galaxy' => 2, 'system' => 3, 'position' => 5]);
        self::assertEqualsWithDelta(111.3125, $crossGalaxy, 1e-6);
    }

    public function testPlanCalculatesTravelTimeAndFuelWithUaMetrics(): void
    {
        $service = new FleetNavigationService();

        $origin = ['galaxy' => 1, 'system' => 1, 'position' => 6];
        $destination = ['galaxy' => 1, 'system' => 2, 'position' => 3];
        $composition = ['heavy_transport' => 2, 'fighter' => 4];
        $shipStats = [
            'heavy_transport' => ['speed' => 7.5, 'fuel_per_hour' => 1280.0],
            'fighter' => ['speed' => 22.5, 'fuel_per_hour' => 9.0],
        ];
        $departure = new DateTimeImmutable('2025-10-01 00:00:00');

        $result = $service->plan(
            $origin,
            $destination,
            $composition,
            $shipStats,
            $departure
        );

        $expectedDistance = 11.4375;
        $expectedSpeed = 7.5;
        $expectedTravelHours = $expectedDistance / $expectedSpeed;
        $expectedTravelSeconds = (int)ceil($expectedTravelHours * 3600);
        $expectedFuel = (int)ceil(($shipStats['heavy_transport']['fuel_per_hour'] * 2 + $shipStats['fighter']['fuel_per_hour'] * 4) * $expectedTravelHours);
        $expectedArrival = $departure->add(new DateInterval('PT' . $expectedTravelSeconds . 'S'));

        self::assertEqualsWithDelta($expectedDistance, $result['distance'], 1e-4);
        self::assertEqualsWithDelta($expectedSpeed, $result['speed'], 1e-4);
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
