<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final class BalanceGlobals
{
    /** @var array<string, int> */
    private array $initialResources = [];

    /** @var array<string, int> */
    private array $initialCapacities = [];

    /**
     * @param array<string, int|float> $initialResources
     * @param array<string, int|float> $initialCapacities
     */
    public function __construct(array $initialResources = [], array $initialCapacities = [])
    {
        foreach ($initialResources as $resource => $value) {
            $this->initialResources[$resource] = (int) round((float) $value);
        }

        foreach ($initialCapacities as $resource => $value) {
            $this->initialCapacities[$resource] = (int) round((float) $value);
        }
    }

    /** @return array<string, int> */
    public function getInitialResources(): array
    {
        return $this->initialResources;
    }

    public function getInitialResource(string $resource): int
    {
        return $this->initialResources[$resource] ?? 0;
    }

    /** @return array<string, int> */
    public function getInitialCapacities(): array
    {
        return $this->initialCapacities;
    }

    public function getInitialCapacity(string $resource): int
    {
        return $this->initialCapacities[$resource] ?? 0;
    }
}
