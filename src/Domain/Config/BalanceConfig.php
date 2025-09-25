<?php

declare(strict_types=1);

namespace App\Domain\Config;

final class BalanceConfig
{
    private float $minimumSpeedModifier;

    private float $maximumDiscount;

    private int $tickDurationSeconds;

    private BalanceRoundingConfig $rounding;

    public function __construct(
        float $minimumSpeedModifier = 0.01,
        float $maximumDiscount = 0.95,
        int $tickDurationSeconds = 3600,
        ?BalanceRoundingConfig $rounding = null,
    ) {
        $this->minimumSpeedModifier = max(0.0, $minimumSpeedModifier);
        $this->maximumDiscount = max(0.0, min(1.0, $maximumDiscount));
        $this->tickDurationSeconds = max(1, $tickDurationSeconds);
        $this->rounding = $rounding ?? new BalanceRoundingConfig();
    }

    public function getMinimumSpeedModifier(): float
    {
        return $this->minimumSpeedModifier;
    }

    public function getMaximumDiscount(): float
    {
        return $this->maximumDiscount;
    }

    public function getTickDurationSeconds(): int
    {
        return $this->tickDurationSeconds;
    }

    public function getRounding(): BalanceRoundingConfig
    {
        return $this->rounding;
    }

    public function getRoundingTolerance(): float
    {
        return $this->rounding->getTolerance();
    }

    public function roundResourceQuantity(float $value): int
    {
        return $this->rounding->roundResource($value);
    }

    public function roundCapacity(float $value): int
    {
        return $this->rounding->roundCapacity($value);
    }

    public function roundProduction(float $value): int
    {
        return $this->rounding->roundProduction($value);
    }

    public function roundEnergyStat(float $value): int
    {
        return $this->rounding->roundEnergyStat($value);
    }

    public function roundEnergyAvailable(float $value): int
    {
        return $this->rounding->roundEnergyAvailable($value);
    }
}
