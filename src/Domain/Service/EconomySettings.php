<?php

namespace App\Domain\Service;

class EconomySettings
{
    private float $buildingCostMultiplier;
    private float $buildingTimeMultiplier;
    private float $buildingProductionMultiplier;
    private float $researchCostMultiplier;
    private float $researchTimeMultiplier;
    private float $shipCostMultiplier;
    private float $shipTimeMultiplier;
    private float $workersHubBonusPerLevel;
    private float $roboticsCenterBonusPerLevel;
    private float $buildingTimeReductionCap;
    private float $researchLabBonusPerLevel;
    private float $researchTimeReductionCap;
    private float $shipyardBonusPerLevel;
    private float $shipTimeReductionCap;

    /**
     * @param array<string, float|int> $config
     */
    public function __construct(array $config = [])
    {
        $this->buildingCostMultiplier = $this->positiveFloat($config['building_cost'] ?? 1.0, 1.0);
        $this->buildingTimeMultiplier = $this->positiveFloat($config['building_time'] ?? 1.0, 1.0);
        $this->buildingProductionMultiplier = $this->positiveFloat($config['building_production'] ?? 1.0, 1.0);
        $this->researchCostMultiplier = $this->positiveFloat($config['research_cost'] ?? 1.0, 1.0);
        $this->researchTimeMultiplier = $this->positiveFloat($config['research_time'] ?? 1.0, 1.0);
        $this->shipCostMultiplier = $this->positiveFloat($config['ship_cost'] ?? 1.0, 1.0);
        $this->shipTimeMultiplier = $this->positiveFloat($config['ship_time'] ?? 1.0, 1.0);

        $this->workersHubBonusPerLevel = max(0.0, (float) ($config['workers_hub_bonus_per_level'] ?? 0.0));
        $this->roboticsCenterBonusPerLevel = max(0.0, (float) ($config['robotics_center_bonus_per_level'] ?? 0.0));
        $this->buildingTimeReductionCap = $this->clamp01($config['building_time_reduction_cap'] ?? 0.0);
        $this->researchLabBonusPerLevel = max(0.0, (float) ($config['research_lab_bonus_per_level'] ?? 0.0));
        $this->researchTimeReductionCap = $this->clamp01($config['research_time_reduction_cap'] ?? 0.0);
        $this->shipyardBonusPerLevel = max(0.0, (float) ($config['shipyard_bonus_per_level'] ?? 0.0));
        $this->shipTimeReductionCap = $this->clamp01($config['ship_time_reduction_cap'] ?? 0.0);
    }

    public function getBuildingCostMultiplier(): float
    {
        return $this->buildingCostMultiplier;
    }

    public function getBuildingTimeMultiplier(): float
    {
        return $this->buildingTimeMultiplier;
    }

    public function getBuildingProductionMultiplier(): float
    {
        return $this->buildingProductionMultiplier;
    }

    public function getResearchCostMultiplier(): float
    {
        return $this->researchCostMultiplier;
    }

    public function getResearchTimeMultiplier(): float
    {
        return $this->researchTimeMultiplier;
    }

    public function getShipCostMultiplier(): float
    {
        return $this->shipCostMultiplier;
    }

    public function getShipTimeMultiplier(): float
    {
        return $this->shipTimeMultiplier;
    }

    /**
     * @param array<string, int> $buildingLevels
     */
    public function getBuildingConstructionFactor(array $buildingLevels): float
    {
        $workersHubLevel = (int) ($buildingLevels['workers_hub'] ?? 0);
        $roboticsCenterLevel = (int) ($buildingLevels['robotics_center'] ?? 0);

        $reduction = ($workersHubLevel * $this->workersHubBonusPerLevel)
            + ($roboticsCenterLevel * $this->roboticsCenterBonusPerLevel);
        $reduction = min($this->buildingTimeReductionCap, $reduction);

        return max(0.05, 1.0 - $reduction);
    }

    public function getResearchSpeedFactor(int $labLevel): float
    {
        $reduction = min($this->researchTimeReductionCap, $labLevel * $this->researchLabBonusPerLevel);

        return max(0.05, 1.0 - $reduction);
    }

    public function getShipConstructionFactor(int $shipyardLevel): float
    {
        $reduction = min($this->shipTimeReductionCap, $shipyardLevel * $this->shipyardBonusPerLevel);

        return max(0.05, 1.0 - $reduction);
    }

    private function positiveFloat(float|int $value, float $fallback): float
    {
        $float = (float) $value;

        if ($float <= 0.0) {
            return $fallback;
        }

        return $float;
    }

    private function clamp01(float|int $value): float
    {
        $float = (float) $value;

        if ($float < 0.0) {
            return 0.0;
        }

        if ($float > 1.0) {
            return 1.0;
        }

        return $float;
    }
}
