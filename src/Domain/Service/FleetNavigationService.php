<?php

declare(strict_types=1);

namespace App\Domain\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

class FleetNavigationService
{
    public function __construct(
        private readonly int $galaxyDistance = 20000,
        private readonly int $systemDistance = 95,
        private readonly int $positionDistance = 5,
        private readonly int $baseDistance = 10
    ) {
    }

    /**
     * @param array{galaxy: int, system: int, position: int} $origin
     * @param array{galaxy: int, system: int, position: int} $destination
     * @param array<string, int> $composition
     * @param array<string, array{speed: int, fuel_per_distance?: float}> $shipStats
     * @param array{speed_bonus?: float, fuel_reduction?: float} $modifiers
     *
     * @return array{distance: int, speed: int, travel_time: int, arrival_time: DateTimeImmutable, fuel: int}
     */
    public function plan(
        array $origin,
        array $destination,
        array $composition,
        array $shipStats,
        DateTimeInterface $departure,
        array $modifiers = [],
        float $speedFactor = 1.0
    ): array {
        if ($composition === [] || array_sum($composition) <= 0) {
            throw new InvalidArgumentException('Fleet composition cannot be empty.');
        }

        $distance = $this->distance($origin, $destination);
        $slowestSpeed = null;
        $fuelConsumption = 0.0;

        foreach ($composition as $shipKey => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            if (!isset($shipStats[$shipKey])) {
                throw new InvalidArgumentException(sprintf('Missing stats for ship "%s".', $shipKey));
            }

            $stats = $shipStats[$shipKey];
            $speed = (int) $stats['speed'];
            if ($speed <= 0) {
                throw new InvalidArgumentException(sprintf('Ship "%s" must have a speed greater than zero.', $shipKey));
            }

            $slowestSpeed = $slowestSpeed === null ? $speed : min($slowestSpeed, $speed);
            $fuelPerDistance = (float) ($stats['fuel_per_distance'] ?? 0.0);
            $fuelConsumption += $fuelPerDistance * $distance * $quantity;
        }

        if ($slowestSpeed === null) {
            throw new InvalidArgumentException('Fleet must contain at least one ship with a quantity greater than zero.');
        }

        $speedBonus = (float) ($modifiers['speed_bonus'] ?? 0.0);
        $speedBonus = max(-0.9, $speedBonus);
        $fuelReduction = (float) ($modifiers['fuel_reduction'] ?? 0.0);
        $fuelReduction = max(0.0, min(0.95, $fuelReduction));
        $effectiveSpeed = $slowestSpeed * max(0.01, $speedFactor) * (1 + $speedBonus);

        $travelSeconds = (int) max(1, ceil($distance / $effectiveSpeed * 3600));
        $departureTime = DateTimeImmutable::createFromInterface($departure);
        $arrival = $departureTime->add(new DateInterval('PT' . $travelSeconds . 'S'));

        $fuelConsumption *= (1 - $fuelReduction);
        $fuel = (int) max(0, ceil($fuelConsumption));

        return [
            'distance' => (int) round($distance),
            'speed' => (int) round($effectiveSpeed),
            'travel_time' => $travelSeconds,
            'arrival_time' => $arrival,
            'fuel' => $fuel,
        ];
    }

    /**
     * @param array{galaxy: int, system: int, position: int} $origin
     * @param array{galaxy: int, system: int, position: int} $destination
     */
    public function distance(array $origin, array $destination): float
    {
        $galaxyDistance = abs($destination['galaxy'] - $origin['galaxy']) * $this->galaxyDistance;
        $systemDistance = abs($destination['system'] - $origin['system']) * $this->systemDistance;
        $positionDistance = abs($destination['position'] - $origin['position']) * $this->positionDistance;

        if ($galaxyDistance === 0 && $systemDistance === 0 && $positionDistance === 0) {
            return (float) $this->baseDistance;
        }

        return $galaxyDistance + $systemDistance + $positionDistance + $this->baseDistance;
    }
}
