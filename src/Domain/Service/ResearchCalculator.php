<?php

namespace App\Domain\Service;

use App\Domain\Entity\ResearchDefinition;

class ResearchCalculator
{
    public function __construct(private readonly EconomySettings $settings)
    {
    }

    /**
     * @return array<string, int>
     */
    public function nextCost(ResearchDefinition $definition, int $currentLevel): array
    {
        $costs = [];
        foreach ($definition->getBaseCost() as $resource => $baseCost) {
            $value = $baseCost * pow($definition->getGrowthCost(), $currentLevel);
            $value *= $this->settings->getResearchCostMultiplier();
            $costs[$resource] = (int) round($value);
        }

        return $costs;
    }

    public function nextTime(ResearchDefinition $definition, int $currentLevel, int $labLevel = 0): int
    {
        $time = $definition->getBaseTime() * pow($definition->getGrowthTime(), $currentLevel);
        $time *= $this->settings->getResearchTimeMultiplier();
        $time *= $this->settings->getResearchSpeedFactor($labLevel);

        return (int) max(1, round($time));
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
