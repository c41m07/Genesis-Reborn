<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\Coordinates;
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
     * @param Coordinates|array{galaxy: int, system: int, position: int} $origin
     * @param Coordinates|array{galaxy: int, system: int, position: int} $destination
     * @param array<string, int> $composition
     * @param array<string, array{speed: int, fuel_per_distance?: float}> $shipStats
     * @param array{speed_bonus?: float, fuel_reduction?: float} $modifiers
     *
     * @return array{distance: int, speed: int, travel_time: int, arrival_time: DateTimeImmutable, fuel: int}
     */
    public function plan(
        Coordinates|array $origin,
        Coordinates|array $destination,
        array             $composition,
        array             $shipStats,
        DateTimeInterface $departure,
        array             $modifiers = [],
        float             $speedFactor = 1.0
    ): array {
        $originCoordinates = $this->normalizeCoordinates($origin);
        $destinationCoordinates = $this->normalizeCoordinates($destination);

        if ($composition === [] || array_sum($composition) <= 0) {
            throw new InvalidArgumentException('Fleet composition cannot be empty.');
        }

        $distance = $this->distance($originCoordinates, $destinationCoordinates);
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
            $speed = (int)$stats['speed'];
            if ($speed <= 0) {
                throw new InvalidArgumentException(sprintf('Ship "%s" must have a speed greater than zero.', $shipKey));
            }

            $slowestSpeed = $slowestSpeed === null ? $speed : min($slowestSpeed, $speed);
            $fuelPerDistance = (float)($stats['fuel_per_distance'] ?? 0.0);
            $fuelConsumption += $fuelPerDistance * $distance * $quantity;
        }

        if ($slowestSpeed === null) {
            throw new InvalidArgumentException('Fleet must contain at least one ship with a quantity greater than zero.');
        }

        $speedBonus = (float)($modifiers['speed_bonus'] ?? 0.0);
        $speedBonus = max(-0.9, $speedBonus);
        $fuelReduction = (float)($modifiers['fuel_reduction'] ?? 0.0);
        $fuelReduction = max(0.0, min(0.95, $fuelReduction));
        $effectiveSpeed = $slowestSpeed * max(0.01, $speedFactor) * (1 + $speedBonus);

        $travelSeconds = (int)max(1, ceil($distance / $effectiveSpeed * 3600));
        $departureTime = DateTimeImmutable::createFromInterface($departure);
        $arrival = $departureTime->add(new DateInterval('PT' . $travelSeconds . 'S'));

        $fuelConsumption *= (1 - $fuelReduction);
        $fuel = (int)max(0, ceil($fuelConsumption));

        return [
            'distance' => (int)round($distance),
            'speed' => (int)round($effectiveSpeed),
            'travel_time' => $travelSeconds,
            'arrival_time' => $arrival,
            'fuel' => $fuel,
        ];
    }

    /**
     * @param Coordinates|array{galaxy: int, system: int, position: int} $origin
     * @param Coordinates|array{galaxy: int, system: int, position: int} $destination
     */
    public function distance(Coordinates|array $origin, Coordinates|array $destination): float
    {
        $originCoordinates = $this->normalizeCoordinates($origin);
        $destinationCoordinates = $this->normalizeCoordinates($destination);

        $galaxyDistance = abs($destinationCoordinates['galaxy'] - $originCoordinates['galaxy']) * $this->galaxyDistance;
        $systemDistance = abs($destinationCoordinates['system'] - $originCoordinates['system']) * $this->systemDistance;
        $positionDistance = abs($destinationCoordinates['position'] - $originCoordinates['position']) * $this->positionDistance;

        if ($galaxyDistance === 0 && $systemDistance === 0 && $positionDistance === 0) {
            return (float)$this->baseDistance;
        }

        return $galaxyDistance + $systemDistance + $positionDistance + $this->baseDistance;
    }

    /**
     * @param Coordinates|array{galaxy: int, system: int, position: int} $coordinates
     *
     * @return array{galaxy: int, system: int, position: int}
     */
    private function normalizeCoordinates(Coordinates|array $coordinates): array
    {
        if ($coordinates instanceof Coordinates) {
            return $coordinates->toArray();
        }

        if (!isset($coordinates['galaxy'], $coordinates['system'], $coordinates['position'])) {
            throw new InvalidArgumentException('Coordinates must contain galaxy, system and position.');
        }

        return [
            'galaxy' => (int)$coordinates['galaxy'],
            'system' => (int)$coordinates['system'],
            'position' => (int)$coordinates['position'],
        ];
    }
}
