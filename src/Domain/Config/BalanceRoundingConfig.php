<?php

declare(strict_types=1);

namespace App\Domain\Config;

use InvalidArgumentException;

final class BalanceRoundingConfig
{
    public const MODE_FLOOR = 'floor';
    public const MODE_ROUND = 'round';
    public const MODE_CEIL = 'ceil';

    private const ALLOWED_MODES = [
        self::MODE_FLOOR,
        self::MODE_ROUND,
        self::MODE_CEIL,
    ];

    private float $tolerance;

    private string $resourceMode;

    private string $capacityMode;

    private string $productionMode;

    private string $energyStatMode;

    private string $energyAvailableMode;

    public function __construct(
        float  $tolerance = 0.000001,
        string $resourceMode = self::MODE_FLOOR,
        string $capacityMode = self::MODE_ROUND,
        string $productionMode = self::MODE_ROUND,
        string $energyStatMode = self::MODE_ROUND,
        string $energyAvailableMode = self::MODE_FLOOR,
    ) {
        $this->tolerance = max(0.0, $tolerance);
        $this->resourceMode = $this->normalizeMode($resourceMode);
        $this->capacityMode = $this->normalizeMode($capacityMode);
        $this->productionMode = $this->normalizeMode($productionMode);
        $this->energyStatMode = $this->normalizeMode($energyStatMode);
        $this->energyAvailableMode = $this->normalizeMode($energyAvailableMode);
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower($mode);

        if (!in_array($mode, self::ALLOWED_MODES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid rounding mode "%s".', $mode));
        }

        return $mode;
    }

    public function getTolerance(): float
    {
        return $this->tolerance;
    }

    public function roundResource(float $value): int
    {
        return $this->applyMode($this->resourceMode, $value);
    }

    private function applyMode(string $mode, float $value): int
    {
        return match ($mode) {
            self::MODE_FLOOR => (int)floor($value + $this->tolerance),
            self::MODE_CEIL => (int)ceil($value - $this->tolerance),
            default => (int)round($value),
        };
    }

    public function roundCapacity(float $value): int
    {
        return $this->applyMode($this->capacityMode, $value);
    }

    public function roundProduction(float $value): int
    {
        return $this->applyMode($this->productionMode, $value);
    }

    public function roundEnergyStat(float $value): int
    {
        return $this->applyMode($this->energyStatMode, $value);
    }

    public function roundEnergyAvailable(float $value): int
    {
        return $this->applyMode($this->energyAvailableMode, $value);
    }
}
