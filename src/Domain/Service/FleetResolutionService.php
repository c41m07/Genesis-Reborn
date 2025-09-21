<?php

namespace App\Domain\Service;

use InvalidArgumentException;

class FleetResolutionService
{
    /**
     * @param array<string, int> $attacker
     * @param array<string, int> $defender
     * @param array<string, array<string, mixed>> $shipDefinitions
     *
     * @return array{
     *     outcome: 'attacker'|'defender'|'stalemate',
     *     attacker_remaining: array<string, int>,
     *     defender_remaining: array<string, int>,
     *     attacker_losses: array<string, int>,
     *     defender_losses: array<string, int>,
     *     rounds: array<int, array{
     *         attacker: array{attack: int, defense: int, losses: array<string, int>, remaining: array<string, int>},
     *         defender: array{attack: int, defense: int, losses: array<string, int>, remaining: array<string, int>},
     *     }>
     * }
     */
    public function resolveBattle(array $attacker, array $defender, array $shipDefinitions, int $maxRounds = 6): array
    {
        if ($maxRounds <= 0) {
            throw new InvalidArgumentException('Max rounds must be greater than zero.');
        }

        $attackerFleet = $this->normalizeFleet($attacker);
        $defenderFleet = $this->normalizeFleet($defender);

        $rounds = [];
        $attackerLosses = $this->emptyFleet(array_keys($attackerFleet));
        $defenderLosses = $this->emptyFleet(array_keys($defenderFleet));
        $allShipKeys = array_unique(array_merge(array_keys($attackerFleet), array_keys($defenderFleet)));

        foreach (array_diff($allShipKeys, array_keys($attackerLosses)) as $key) {
            $attackerLosses[$key] = 0;
        }
        foreach (array_diff($allShipKeys, array_keys($defenderLosses)) as $key) {
            $defenderLosses[$key] = 0;
        }

        for ($round = 1; $round <= $maxRounds; $round++) {
            if ($this->isFleetDestroyed($attackerFleet) || $this->isFleetDestroyed($defenderFleet)) {
                break;
            }

            $attackerAttack = $this->totalAttack($attackerFleet, $shipDefinitions);
            $defenderAttack = $this->totalAttack($defenderFleet, $shipDefinitions);
            $attackerDefense = $this->totalDefense($attackerFleet, $shipDefinitions);
            $defenderDefense = $this->totalDefense($defenderFleet, $shipDefinitions);

            $attackerCasualties = $this->calculateCasualties($defenderAttack, $attackerDefense, $attackerFleet, $shipDefinitions);
            $defenderCasualties = $this->calculateCasualties($attackerAttack, $defenderDefense, $defenderFleet, $shipDefinitions);

            $rounds[$round] = [
                'attacker' => [
                    'attack' => $attackerAttack,
                    'defense' => $attackerDefense,
                    'losses' => $attackerCasualties,
                    'remaining' => $attackerFleet,
                ],
                'defender' => [
                    'attack' => $defenderAttack,
                    'defense' => $defenderDefense,
                    'losses' => $defenderCasualties,
                    'remaining' => $defenderFleet,
                ],
            ];

            $attackerLosses = $this->sumLosses($attackerLosses, $attackerCasualties);
            $defenderLosses = $this->sumLosses($defenderLosses, $defenderCasualties);

            $attackerFleet = $this->applyCasualties($attackerFleet, $attackerCasualties);
            $defenderFleet = $this->applyCasualties($defenderFleet, $defenderCasualties);
        }

        $outcome = $this->determineOutcome($attackerFleet, $defenderFleet);

        ksort($attackerFleet);
        ksort($defenderFleet);
        ksort($attackerLosses);
        ksort($defenderLosses);

        return [
            'outcome' => $outcome,
            'attacker_remaining' => $attackerFleet,
            'defender_remaining' => $defenderFleet,
            'attacker_losses' => $attackerLosses,
            'defender_losses' => $defenderLosses,
            'rounds' => $rounds,
        ];
    }

    /**
     * @param array<string, int> $fleet
     *
     * @return array<string, int>
     */
    private function normalizeFleet(array $fleet): array
    {
        $normalized = [];

        foreach ($fleet as $key => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                continue;
            }

            $normalized[$key] = $quantity;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param string[] $keys
     *
     * @return array<string, int>
     */
    private function emptyFleet(array $keys): array
    {
        $empty = [];
        foreach ($keys as $key) {
            $empty[$key] = 0;
        }

        return $empty;
    }

    /**
     * @param array<string, int> $fleet
     * @param array<string, array<string, mixed>> $shipDefinitions
     */
    private function totalAttack(array $fleet, array $shipDefinitions): int
    {
        $total = 0.0;
        foreach ($fleet as $key => $quantity) {
            $attack = $this->statForShip($shipDefinitions, $key, 'attaque');
            $total += $quantity * $attack;
        }

        return (int) round($total);
    }

    /**
     * @param array<string, int> $fleet
     * @param array<string, array<string, mixed>> $shipDefinitions
     */
    private function totalDefense(array $fleet, array $shipDefinitions): int
    {
        $total = 0.0;
        foreach ($fleet as $key => $quantity) {
            $defense = $this->statForShip($shipDefinitions, $key, 'défense');
            $total += $quantity * $defense;
        }

        return (int) round($total);
    }

    /**
     * @param array<string, int> $fleet
     * @param array<string, int> $casualties
     *
     * @return array<string, int>
     */
    private function applyCasualties(array $fleet, array $casualties): array
    {
        foreach ($casualties as $key => $loss) {
            if (!isset($fleet[$key])) {
                continue;
            }

            $fleet[$key] = max(0, $fleet[$key] - $loss);
            if ($fleet[$key] === 0) {
                unset($fleet[$key]);
            }
        }

        ksort($fleet);

        return $fleet;
    }

    /**
     * @param array<string, int> $existing
     * @param array<string, int> $additional
     *
     * @return array<string, int>
     */
    private function sumLosses(array $existing, array $additional): array
    {
        foreach ($additional as $key => $value) {
            if (!isset($existing[$key])) {
                $existing[$key] = 0;
            }

            $existing[$key] += $value;
        }

        return $existing;
    }

    /**
     * @param array<string, int> $fleet
     */
    private function isFleetDestroyed(array $fleet): bool
    {
        foreach ($fleet as $quantity) {
            if ($quantity > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, int> $fleet
     * @param array<string, array<string, mixed>> $shipDefinitions
     *
     * @return array<string, int>
     */
    private function calculateCasualties(int $incomingAttack, int $targetDefense, array $fleet, array $shipDefinitions): array
    {
        if ($incomingAttack <= 0 || $this->isFleetDestroyed($fleet)) {
            return $this->emptyFleet(array_keys($fleet));
        }

        if ($targetDefense <= 0) {
            return $fleet;
        }

        $casualties = [];
        $remainingDamage = (float) $incomingAttack;
        $defenseTotal = (float) max(1, $targetDefense);

        foreach ($fleet as $key => $quantity) {
            $shipDefense = max(1.0, $this->statForShip($shipDefinitions, $key, 'défense'));
            $shipDefenseTotal = $quantity * $shipDefense;

            if ($shipDefenseTotal <= 0) {
                $casualties[$key] = $quantity;
                continue;
            }

            $proportion = $shipDefenseTotal / $defenseTotal;
            $damageForShip = $incomingAttack * $proportion;
            $destroyed = (int) floor($damageForShip / $shipDefense);

            if ($destroyed > $quantity) {
                $destroyed = $quantity;
            }

            // ensure we do not lose damage share completely when small amounts accumulate
            if ($destroyed < $quantity) {
                $remainingDamage -= $destroyed * $shipDefense;
            } else {
                $remainingDamage -= $quantity * $shipDefense;
            }

            if ($destroyed < $quantity && $remainingDamage > 0 && $damageForShip > 0 && ($damageForShip / $shipDefense) > $destroyed) {
                $destroyed = min($quantity, $destroyed + 1);
                $remainingDamage -= $shipDefense;
            }

            $casualties[$key] = max(0, $destroyed);
        }

        return $casualties;
    }

    /**
     * @param array<string, array<string, mixed>> $shipDefinitions
     */
    private function statForShip(array $shipDefinitions, string $key, string $stat): float
    {
        $definition = $shipDefinitions[$key] ?? null;
        if (!is_array($definition) || empty($definition['stats']) || !is_array($definition['stats'])) {
            return 0.0;
        }

        $stats = $definition['stats'];
        if (!isset($stats[$stat])) {
            return 0.0;
        }

        return (float) $stats[$stat];
    }

    /**
     * @param array<string, int> $attackerRemaining
     * @param array<string, int> $defenderRemaining
     *
     * @return 'attacker'|'defender'|'stalemate'
     */
    private function determineOutcome(array $attackerRemaining, array $defenderRemaining): string
    {
        $attackerDestroyed = $this->isFleetDestroyed($attackerRemaining);
        $defenderDestroyed = $this->isFleetDestroyed($defenderRemaining);

        if ($attackerDestroyed && $defenderDestroyed) {
            return 'stalemate';
        }

        if ($defenderDestroyed) {
            return 'attacker';
        }

        if ($attackerDestroyed) {
            return 'defender';
        }

        return 'stalemate';
    }
}
