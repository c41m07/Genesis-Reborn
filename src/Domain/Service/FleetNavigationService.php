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
    private const HUB_POSITION = 16;
    private const UNITS_PER_ASTRONOMICAL_UNIT = 16.0;
    private const GALAXY_JUMP_COST = 100.0;
    private const SYSTEM_JUMP_COST = 10.0;

    /**
     * @param Coordinates|array{galaxy: int, system: int, position: int} $origin
     * @param Coordinates|array{galaxy: int, system: int, position: int} $destination
     * @param array<string, int> $composition
     * @param array<string, array{speed: float, fuel_per_hour?: float}> $shipStats
     * @param array{speed_bonus?: float, fuel_reduction?: float} $modifiers
     *
     * @return array{distance: float, speed: float, travel_time: int, arrival_time: DateTimeImmutable, fuel: int}
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
        $totalFuelPerHour = 0.0;

        foreach ($composition as $shipKey => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            if (!isset($shipStats[$shipKey])) {
                throw new InvalidArgumentException(sprintf('Missing stats for ship "%s".', $shipKey));
            }

            $stats = $shipStats[$shipKey];
            $speed = (float)($stats['speed'] ?? 0.0);
            if ($speed <= 0) {
                throw new InvalidArgumentException(sprintf('Ship "%s" must have a speed greater than zero.', $shipKey));
            }

            $slowestSpeed = $slowestSpeed === null ? $speed : min($slowestSpeed, $speed);
            $fuelPerHour = max(0.0, (float)($stats['fuel_per_hour'] ?? 0.0));
            $totalFuelPerHour += $fuelPerHour * $quantity;
        }

        if ($slowestSpeed === null) {
            throw new InvalidArgumentException('Fleet must contain at least one ship with a quantity greater than zero.');
        }

        $speedBonus = (float)($modifiers['speed_bonus'] ?? 0.0);
        $speedBonus = max(-0.9, $speedBonus);
        $fuelReduction = (float)($modifiers['fuel_reduction'] ?? 0.0);
        $fuelReduction = max(0.0, min(0.95, $fuelReduction));
        $effectiveSpeed = $slowestSpeed * max(0.01, $speedFactor) * (1 + $speedBonus);

        $travelHours = $effectiveSpeed > 0 ? ($distance / $effectiveSpeed) : 0.0;
        $rawTravelSeconds = (int)ceil($travelHours * 3600);
        $travelSeconds = $distance > 0 ? (int)max(1, $rawTravelSeconds) : 0;
        $departureTime = DateTimeImmutable::createFromInterface($departure);
        $arrival = $departureTime->add(new DateInterval('PT' . $travelSeconds . 'S'));

        $fuelConsumption = $totalFuelPerHour * $travelHours;
        $fuelConsumption *= (1 - $fuelReduction);
        $fuel = (int)max(0, ceil($fuelConsumption));

        return [
            'distance' => round($distance, 4),
            'speed' => round($effectiveSpeed, 4),
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

        if ($originCoordinates === $destinationCoordinates) {
            return 0.0;
        }

        if ($originCoordinates['galaxy'] === $destinationCoordinates['galaxy']
            && $originCoordinates['system'] === $destinationCoordinates['system']
        ) {
            $units = abs($destinationCoordinates['position'] - $originCoordinates['position']);

            return $units / self::UNITS_PER_ASTRONOMICAL_UNIT;
        }

        $distanceUa = (
            abs(self::HUB_POSITION - $originCoordinates['position'])
            + abs(self::HUB_POSITION - $destinationCoordinates['position'])
        ) / self::UNITS_PER_ASTRONOMICAL_UNIT;

        $galaxyDiff = abs($destinationCoordinates['galaxy'] - $originCoordinates['galaxy']);
        if ($galaxyDiff > 0) {
            $distanceUa += $galaxyDiff * self::GALAXY_JUMP_COST;
        }

        $systemDiff = abs($destinationCoordinates['system'] - $originCoordinates['system']);
        if ($systemDiff > 0) {
            $distanceUa += self::SYSTEM_JUMP_COST;
        }

        return $distanceUa;
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
