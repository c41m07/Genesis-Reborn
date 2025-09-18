<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Service\FleetResolutionService;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class FleetResolutionServiceTest extends TestCase
{
    public function testAdvanceResolvesArrivalsAndReturns(): void
    {
        $service = new FleetResolutionService();

        $now = new DateTimeImmutable('2025-09-20 12:00:00');
        $pveCalled = false;
        $exploreCalled = false;

        $pveResolver = function (array $fleet) use (&$pveCalled) {
            $pveCalled = true;
            TestCase::assertSame('pirate_outpost', $fleet['mission_payload']['mission']);

            return [
                'status' => 'returning',
                'payload' => ['loot' => ['metal' => 500]],
                'return_delay' => 1800,
            ];
        };

        $explorationResolver = function (array $fleet) use (&$exploreCalled) {
            $exploreCalled = true;

            return [
                'status' => 'returning',
                'payload' => ['artifact' => 'ancient'],
                'return_delay' => 3600,
            ];
        };

        $fleets = [
            [
                'id' => 1,
                'player_id' => 10,
                'mission_type' => 'pve',
                'status' => 'outbound',
                'arrival_at' => new DateTimeImmutable('2025-09-20 11:45:00'),
                'return_at' => null,
                'travel_time_seconds' => 1800,
                'mission_payload' => ['mission' => 'pirate_outpost'],
            ],
            [
                'id' => 2,
                'player_id' => 20,
                'mission_type' => 'transport',
                'status' => 'returning',
                'arrival_at' => new DateTimeImmutable('2025-09-20 10:40:00'),
                'return_at' => new DateTimeImmutable('2025-09-20 11:55:00'),
                'travel_time_seconds' => 2400,
                'mission_payload' => [],
            ],
            [
                'id' => 3,
                'player_id' => 10,
                'mission_type' => 'explore',
                'status' => 'outbound',
                'arrival_at' => new DateTimeImmutable('2025-09-20 11:50:00'),
                'return_at' => null,
                'travel_time_seconds' => 2400,
                'mission_payload' => [],
            ],
        ];

        $result = $service->advance($fleets, $now, $pveResolver, $explorationResolver);

        self::assertTrue($pveCalled);
        self::assertTrue($exploreCalled);

        $fleetOne = $result[0];
        self::assertSame('returning', $fleetOne['status']);
        self::assertEquals($now, $fleetOne['arrival_at']);
        self::assertEquals($now->add(new DateInterval('PT1800S')), $fleetOne['return_at']);
        self::assertSame(['loot' => ['metal' => 500]], $fleetOne['mission_payload']['last_resolution']);

        $fleetTwo = $result[1];
        self::assertSame('completed', $fleetTwo['status']);
        self::assertEquals($now, $fleetTwo['return_at']);

        $fleetThree = $result[2];
        self::assertSame('returning', $fleetThree['status']);
        self::assertEquals($now->add(new DateInterval('PT3600S')), $fleetThree['return_at']);
        self::assertSame(['artifact' => 'ancient'], $fleetThree['mission_payload']['last_resolution']);
    }
}
