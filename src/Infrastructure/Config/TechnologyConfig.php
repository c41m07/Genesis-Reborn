<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use InvalidArgumentException;

final class TechnologyConfig
{
    private string $key;

    private string $label;

    private string $category;

    private string $description;

    /** @var array<string, int> */
    private array $baseCost = [];

    private int $baseTime = 0;

    private float $growthCost = 1.0;

    private float $growthTime = 1.0;

    private int $maxLevel = 1;

    /** @var array<string, int> */
    private array $requires = [];

    private int $requiresLab = 0;

    private ?string $image = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $key, array $data)
    {
        $this->key = $key;
        $this->label = (string)($data['label'] ?? $key);
        $this->category = (string)($data['category'] ?? 'Divers');
        $this->description = (string)($data['description'] ?? '');

        $baseCost = $data['base_cost'] ?? [];
        if (!is_array($baseCost)) {
            throw new InvalidArgumentException(sprintf('Invalid base_cost definition for technology "%s".', $key));
        }

        foreach ($baseCost as $resource => $value) {
            $this->baseCost[$resource] = (int)round((float)$value);
        }

        $this->baseTime = (int)round((float)($data['base_time'] ?? 0));
        $this->growthCost = (float)($data['growth_cost'] ?? 1.0);
        $this->growthTime = (float)($data['growth_time'] ?? 1.0);
        $this->maxLevel = max(0, (int)($data['max_level'] ?? 10));

        $requires = $data['requires'] ?? [];
        if (!is_array($requires)) {
            throw new InvalidArgumentException(sprintf('Invalid requires definition for technology "%s".', $key));
        }

        foreach ($requires as $researchKey => $level) {
            $this->requires[$researchKey] = max(0, (int)$level);
        }

        $this->requiresLab = max(0, (int)($data['requires_lab'] ?? 0));

        if (!empty($data['image'])) {
            $this->image = (string)$data['image'];
        }
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return array<string, int> */
    public function getBaseCost(): array
    {
        return $this->baseCost;
    }

    public function getBaseTime(): int
    {
        return $this->baseTime;
    }

    public function getGrowthCost(): float
    {
        return $this->growthCost;
    }

    public function getGrowthTime(): float
    {
        return $this->growthTime;
    }

    public function getMaxLevel(): int
    {
        return $this->maxLevel;
    }

    /** @return array<string, int> */
    public function getRequires(): array
    {
        return $this->requires;
    }

    public function getRequiresLab(): int
    {
        return $this->requiresLab;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }
}
