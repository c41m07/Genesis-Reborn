<?php

namespace App\Domain\Service;

use App\Domain\Entity\ResearchDefinition;

class ResearchCalculator
{
    public function __construct(private readonly float $labSpeedBonusPerLevel = 0.0)
    {
    }

    /**
     * @return array<string, int>
     */
    public function nextCost(ResearchDefinition $definition, int $currentLevel): array
    {
        $costs = [];
        foreach ($definition->getBaseCost() as $resource => $baseCost) {
            $costs[$resource] = (int) round($baseCost * pow($definition->getGrowthCost(), $currentLevel));
        }

        return $costs;
    }

    public function nextTime(ResearchDefinition $definition, int $currentLevel, int $labLevel): int
    {
        $baseDuration = (float) $definition->getBaseTime() * pow($definition->getGrowthTime(), $currentLevel);
        $effectiveLabLevel = max(0, $labLevel);
        $bonusPerLevel = max(0.0, $this->labSpeedBonusPerLevel);
        $speedMultiplier = 1.0 + ($effectiveLabLevel * $bonusPerLevel);

        if ($speedMultiplier > 0.0) {
            $baseDuration /= $speedMultiplier;
        }

        return max(1, (int) round($baseDuration));
    }

    public function labSpeedBonus(int $labLevel): float
    {
        $effectiveLabLevel = max(0, $labLevel);
        $bonusPerLevel = max(0.0, $this->labSpeedBonusPerLevel);

        if ($effectiveLabLevel === 0 || $bonusPerLevel <= 0.0) {
            return 0.0;
        }

        return $effectiveLabLevel * $bonusPerLevel;
    }

    /**
     * @param array<string, int> $researchLevels
     * @param array<string, array{label: string}> $researchCatalog
     *
     * @return array{ok: bool, missing: array<int, array{type: string, key: string, label: string, level: int, current: int}>}
     */
    public function checkRequirements(
        ResearchDefinition $definition,
        array $researchLevels,
        int $labLevel,
        array $researchCatalog = []
    ): array {
        $missing = [];

        if ($labLevel < $definition->getRequiresLab()) {
            $missing[] = [
                'type' => 'building',
                'key' => 'research_lab',
                'label' => 'Laboratoire de recherche',
                'level' => $definition->getRequiresLab(),
                'current' => $labLevel,
            ];
        }

        foreach ($definition->getRequires() as $key => $requiredLevel) {
            $currentLevel = (int) ($researchLevels[$key] ?? 0);
            if ($currentLevel < $requiredLevel) {
                $missing[] = [
                    'type' => 'research',
                    'key' => $key,
                    'label' => $researchCatalog[$key]['label'] ?? $key,
                    'level' => (int) $requiredLevel,
                    'current' => $currentLevel,
                ];
            }
        }

        return [
            'ok' => empty($missing),
            'missing' => $missing,
        ];
    }
}
