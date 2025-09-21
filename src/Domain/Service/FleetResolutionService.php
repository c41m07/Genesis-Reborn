<?php

namespace App\Domain\Service;

use App\Domain\Battle\DTO\AttackingFleetDTO;
use App\Domain\Battle\DTO\DefendingFleetDTO;
use App\Domain\Battle\DTO\FleetBattleResultDTO;
use App\Domain\Battle\DTO\FleetBattleRoundDTO;
use App\Infrastructure\Config\BalanceConfigLoader;
use InvalidArgumentException;

class FleetResolutionService
{
    public function __construct(
        private readonly ShipCatalog $shipCatalog,
        private readonly BalanceConfigLoader $balanceConfigLoader
    ) {
    }

    public function resolveBattle(AttackingFleetDTO $attacker, DefendingFleetDTO $defender): FleetBattleResultDTO
    {
        $config = $this->balanceConfigLoader->all();
        $combatConfig = is_array($config['combat'] ?? null) ? $config['combat'] : [];

        $maxRounds = max(1, (int) ($combatConfig['rounds'] ?? 1));
        $minLossRatio = max(0.0, (float) ($combatConfig['min_losses_ratio'] ?? 0.0));

        $retreatConfig = is_array($combatConfig['retreat_threshold'] ?? null) ? $combatConfig['retreat_threshold'] : [];
        $attackerRetreatThreshold = $this->clampRatio((float) ($retreatConfig['attacker'] ?? 0.0));
        $defenderRetreatThreshold = $this->clampRatio((float) ($retreatConfig['defender'] ?? 0.0));

        $statMultipliers = is_array($combatConfig['stat_multipliers'] ?? null) ? $combatConfig['stat_multipliers'] : [];
        $attackStatMultiplier = max(0.0, (float) ($statMultipliers['attack'] ?? 1.0));
        $hullPerDefense = max(0.0, (float) ($statMultipliers['hull_per_defense'] ?? 1.0));
        $baseHull = max(0.0, (float) ($statMultipliers['base_hull'] ?? 0.0));

        $attackMultipliers = is_array($combatConfig['attack_multipliers'] ?? null) ? $combatConfig['attack_multipliers'] : [];
        $attackerDamageMultiplier = max(0.0, (float) ($attackMultipliers['attacker'] ?? 1.0));
        $defenderDamageMultiplier = max(0.0, (float) ($attackMultipliers['defender'] ?? 1.0));

        $targetPriorities = is_array($config['target_priorities'] ?? null) ? $config['target_priorities'] : [];
        $damageModifiers = is_array($config['damage_modifiers'] ?? null) ? $config['damage_modifiers'] : [];

        $defaultPriority = $this->sanitizePriorityList($targetPriorities['default'] ?? []);
        $defaultDamageModifier = (float) ($combatConfig['default_damage_modifier'] ?? ($damageModifiers['default'] ?? 1.0));
        if ($defaultDamageModifier <= 0.0) {
            $defaultDamageModifier = 1.0;
        }

        $attackerDamageMultiplier *= $this->extractMultiplier($attacker->getModifiers(), 'damage');
        $defenderDamageMultiplier *= $this->extractMultiplier($defender->getModifiers(), 'damage');
        $attackerHullMultiplier = $this->extractMultiplier($attacker->getModifiers(), 'hull');
        $defenderHullMultiplier = $this->extractMultiplier($defender->getModifiers(), 'hull');

        $attackerFleet = $this->buildFleet($attacker->getComposition(), $hullPerDefense, $baseHull, $attackerHullMultiplier);
        $defenderFleet = $this->buildFleet($defender->getComposition(), $hullPerDefense, $baseHull, $defenderHullMultiplier);

        if (!$this->hasUnits($attackerFleet) || !$this->hasUnits($defenderFleet)) {
            $winner = 'draw';
            if (!$this->hasUnits($attackerFleet) && $this->hasUnits($defenderFleet)) {
                $winner = 'defender';
            } elseif ($this->hasUnits($attackerFleet) && !$this->hasUnits($defenderFleet)) {
                $winner = 'attacker';
            }

            return new FleetBattleResultDTO(
                $winner,
                $this->extractQuantities($attackerFleet),
                $this->extractQuantities($defenderFleet),
                [],
                false,
                false
            );
        }

        $initialAttackerStrength = max(1.0, $this->calculateFleetStrength($attackerFleet));
        $initialDefenderStrength = max(1.0, $this->calculateFleetStrength($defenderFleet));

        $roundSummaries = [];
        $attackerRetreated = false;
        $defenderRetreated = false;

        for ($round = 1; $round <= $maxRounds; $round++) {
            $attackerUnitsBefore = $this->countUnits($attackerFleet);
            $defenderUnitsBefore = $this->countUnits($defenderFleet);

            if ($attackerUnitsBefore === 0 || $defenderUnitsBefore === 0) {
                break;
            }

            $defenderLosses = $this->calculateCasualties(
                $attackerFleet,
                $defenderFleet,
                $targetPriorities,
                $defaultPriority,
                $damageModifiers,
                $attackStatMultiplier,
                $attackerDamageMultiplier,
                $defaultDamageModifier
            );

            $attackerLosses = $this->calculateCasualties(
                $defenderFleet,
                $attackerFleet,
                $targetPriorities,
                $defaultPriority,
                $damageModifiers,
                $attackStatMultiplier,
                $defenderDamageMultiplier,
                $defaultDamageModifier
            );

            $this->enforceMinimumLosses($attackerLosses, $attackerUnitsBefore, $minLossRatio, $attackerFleet);
            $this->enforceMinimumLosses($defenderLosses, $defenderUnitsBefore, $minLossRatio, $defenderFleet);

            $this->applyLosses($attackerFleet, $attackerLosses);
            $this->applyLosses($defenderFleet, $defenderLosses);

            $roundSummaries[] = new FleetBattleRoundDTO(
                $round,
                $attackerLosses,
                $defenderLosses,
                $this->extractQuantities($attackerFleet),
                $this->extractQuantities($defenderFleet)
            );

            $currentAttackerStrength = $this->calculateFleetStrength($attackerFleet);
            $currentDefenderStrength = $this->calculateFleetStrength($defenderFleet);

            if (!$attackerRetreated && $attackerRetreatThreshold > 0.0 && $currentAttackerStrength <= $initialAttackerStrength * $attackerRetreatThreshold) {
                $attackerRetreated = true;
            }

            if (!$defenderRetreated && $defenderRetreatThreshold > 0.0 && $currentDefenderStrength <= $initialDefenderStrength * $defenderRetreatThreshold) {
                $defenderRetreated = true;
            }

            if ($attackerRetreated || $defenderRetreated) {
                break;
            }
        }

        $attackerUnits = $this->countUnits($attackerFleet);
        $defenderUnits = $this->countUnits($defenderFleet);

        $winner = 'draw';
        if ($attackerUnits > 0 && $defenderUnits === 0) {
            $winner = 'attacker';
        } elseif ($defenderUnits > 0 && $attackerUnits === 0) {
            $winner = 'defender';
        } elseif ($defenderRetreated && !$attackerRetreated && $attackerUnits > 0) {
            $winner = 'attacker';
        } elseif ($attackerRetreated && !$defenderRetreated && $defenderUnits > 0) {
            $winner = 'defender';
        }

        return new FleetBattleResultDTO(
            $winner,
            $this->extractQuantities($attackerFleet),
            $this->extractQuantities($defenderFleet),
            $roundSummaries,
            $attackerRetreated,
            $defenderRetreated
        );
    }

    /**
     * @param array<string, int> $composition
     *
     * @return array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}>
     */
    private function buildFleet(array $composition, float $hullPerDefense, float $baseHull, float $hullMultiplier): array
    {
        $fleet = [];

        foreach ($composition as $shipKey => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                continue;
            }

            try {
                $definition = $this->shipCatalog->get($shipKey);
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException(sprintf('Unknown ship "%s" in fleet composition.', $shipKey), 0, $exception);
            }

            $stats = $definition->getStats();
            $attack = max(0.0, (float) ($stats['attaque'] ?? 0.0));
            $defense = max(0.0, (float) ($stats['défense'] ?? 0.0));

            $hull = ($baseHull + $defense * $hullPerDefense) * max(0.1, $hullMultiplier);
            $hull = max(1.0, $hull);

            $fleet[$shipKey] = [
                'key' => $shipKey,
                'label' => $definition->getLabel(),
                'quantity' => $quantity,
                'attack' => $attack,
                'defense' => $defense,
                'hull' => $hull,
            ];
        }

        ksort($fleet);

        return $fleet;
    }

    /**
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $fleet
     */
    private function calculateFleetStrength(array $fleet): float
    {
        $strength = 0.0;

        foreach ($fleet as $ship) {
            $strength += $ship['quantity'] * $ship['hull'];
        }

        return $strength;
    }

    /**
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $fleet
     */
    private function countUnits(array $fleet): int
    {
        $total = 0;
        foreach ($fleet as $ship) {
            $total += $ship['quantity'];
        }

        return $total;
    }

    /**
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $fleet
     */
    private function hasUnits(array $fleet): bool
    {
        foreach ($fleet as $ship) {
            if ($ship['quantity'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $attackingFleet
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $defendingFleet
     * @param array<string, array<int, string>> $priorities
     * @param array<int, string> $defaultPriority
     * @param array<string, mixed> $damageModifiers
     *
     * @return array<string, int>
     */
    private function calculateCasualties(
        array $attackingFleet,
        array $defendingFleet,
        array $priorities,
        array $defaultPriority,
        array $damageModifiers,
        float $attackStatMultiplier,
        float $sideDamageMultiplier,
        float $defaultDamageModifier
    ): array {
        $damagePool = [];

        foreach ($attackingFleet as $shipKey => $ship) {
            $quantity = $ship['quantity'];
            if ($quantity <= 0) {
                continue;
            }

            $damage = $quantity * $ship['attack'] * $attackStatMultiplier * $sideDamageMultiplier;
            if ($damage <= 0.0) {
                continue;
            }

            $targets = $this->resolveTargetOrder($shipKey, $priorities, $defaultPriority, $defendingFleet);
            foreach ($targets as $targetKey) {
                if (!isset($defendingFleet[$targetKey]) || $defendingFleet[$targetKey]['quantity'] <= 0) {
                    continue;
                }

                $modifier = $this->resolveDamageModifier($shipKey, $targetKey, $damageModifiers, $defaultDamageModifier);
                if ($modifier <= 0.0) {
                    continue;
                }

                $damagePool[$targetKey] = ($damagePool[$targetKey] ?? 0.0) + $damage * $modifier;
                break;
            }
        }

        $losses = [];

        foreach ($damagePool as $targetKey => $damage) {
            if (!isset($defendingFleet[$targetKey])) {
                continue;
            }

            $hull = $defendingFleet[$targetKey]['hull'];
            if ($hull <= 0.0) {
                $losses[$targetKey] = $defendingFleet[$targetKey]['quantity'];
                continue;
            }

            $kills = (int) floor($damage / $hull);
            if ($kills <= 0) {
                continue;
            }

            $losses[$targetKey] = min($kills, $defendingFleet[$targetKey]['quantity']);
        }

        return $losses;
    }

    /**
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $fleet
     * @param array<string, int> $losses
     */
    private function applyLosses(array &$fleet, array $losses): void
    {
        foreach ($losses as $shipKey => $lost) {
            if (!isset($fleet[$shipKey])) {
                continue;
            }

            $fleet[$shipKey]['quantity'] = max(0, $fleet[$shipKey]['quantity'] - (int) $lost);
        }
    }

    /**
     * @param array<string, int> $losses
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $fleet
     */
    private function enforceMinimumLosses(array &$losses, int $unitsBefore, float $minLossRatio, array $fleet): void
    {
        if ($unitsBefore <= 0 || $minLossRatio <= 0.0) {
            return;
        }

        $currentLosses = 0;
        foreach ($losses as $value) {
            $currentLosses += (int) $value;
        }

        $minimum = (int) ceil($unitsBefore * $minLossRatio);
        if ($minimum <= $currentLosses) {
            return;
        }

        $additional = min($minimum - $currentLosses, $unitsBefore);
        if ($additional <= 0) {
            return;
        }

        $keys = array_keys($fleet);
        usort($keys, function (string $a, string $b) use ($fleet): int {
            $compare = $fleet[$b]['hull'] <=> $fleet[$a]['hull'];
            if ($compare === 0) {
                return $fleet[$b]['quantity'] <=> $fleet[$a]['quantity'];
            }

            return $compare;
        });

        foreach ($keys as $key) {
            if ($additional <= 0) {
                break;
            }

            if (!isset($fleet[$key])) {
                continue;
            }

            $available = $fleet[$key]['quantity'] - ($losses[$key] ?? 0);
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $additional);
            $losses[$key] = ($losses[$key] ?? 0) + $take;
            $additional -= $take;
        }
    }

    /**
     * @return array<string, int>
     */
    private function extractQuantities(array $fleet): array
    {
        $quantities = [];

        foreach ($fleet as $shipKey => $data) {
            $quantities[$shipKey] = (int) $data['quantity'];
        }

        ksort($quantities);

        return $quantities;
    }

    /**
     * @param array<int, string> $priority
     *
     * @return array<int, string>
     */
    private function sanitizePriorityList(array $priority): array
    {
        $sanitized = [];
        foreach ($priority as $entry) {
            $entry = (string) $entry;
            if ($entry === '') {
                continue;
            }

            if (!in_array($entry, $sanitized, true)) {
                $sanitized[] = $entry;
            }
        }

        return $sanitized;
    }

    /**
     * @param array<string, array<int, string>> $priorities
     * @param array<string, array{key: string, label: string, quantity: int, attack: float, defense: float, hull: float}> $defenderFleet
     *
     * @return array<int, string>
     */
    private function resolveTargetOrder(string $attackerKey, array $priorities, array $defaultPriority, array $defenderFleet): array
    {
        $specific = [];
        if (isset($priorities[$attackerKey]) && is_array($priorities[$attackerKey])) {
            $specific = $this->sanitizePriorityList($priorities[$attackerKey]);
        }

        $ordered = $specific !== [] ? $specific : $defaultPriority;

        $available = [];
        foreach ($defenderFleet as $shipKey => $data) {
            if ($data['quantity'] > 0) {
                $available[] = $shipKey;
            }
        }

        $result = [];
        foreach ($ordered as $targetKey) {
            if (in_array($targetKey, $available, true) && !in_array($targetKey, $result, true)) {
                $result[] = $targetKey;
            }
        }

        foreach ($available as $targetKey) {
            if (!in_array($targetKey, $result, true)) {
                $result[] = $targetKey;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $modifiers
     */
    private function resolveDamageModifier(string $attackerKey, string $targetKey, array $modifiers, float $default): float
    {
        $default = max(0.0, $default);

        if (isset($modifiers[$attackerKey]) && is_array($modifiers[$attackerKey])) {
            $specific = $modifiers[$attackerKey];
            if (array_key_exists($targetKey, $specific)) {
                return max(0.0, (float) $specific[$targetKey]);
            }

            if (array_key_exists('default', $specific)) {
                return max(0.0, (float) $specific['default']);
            }
        }

        if (array_key_exists('default', $modifiers)) {
            return max(0.0, (float) $modifiers['default']);
        }

        return $default;
    }

    private function clampRatio(float $value): float
    {
        if ($value <= 0.0) {
            return 0.0;
        }

        if ($value >= 1.0) {
            return 1.0;
        }

        return $value;
    }

    /**
     * @param array<string, float> $modifiers
     */
    private function extractMultiplier(array $modifiers, string $type): float
    {
        $multiplierKey = $type . '_multiplier';
        $bonusKey = $type . '_bonus';

        if (array_key_exists($multiplierKey, $modifiers)) {
            $value = (float) $modifiers[$multiplierKey];
            return $value > 0.0 ? $value : 1.0;
        }

        if (array_key_exists($bonusKey, $modifiers)) {
            $value = 1.0 + (float) $modifiers[$bonusKey];
            return $value > 0.0 ? $value : 1.0;
        }

        return 1.0;
    }
}
