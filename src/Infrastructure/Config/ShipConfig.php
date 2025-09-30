<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use InvalidArgumentException;

final class ShipConfig
{
    private string $key;

    private string $label;

    private string $category;

    private string $role;

    private string $description;

    /** @var array<string, int> */
    private array $baseCost = [];

    private int $buildTime = 0;

    /** @var array<string, int> */
    private array $stats = [];

    /** @var array<string, int> */
    private array $requiresResearch = [];

    private ?string $image = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $key, array $data)
    {
        $this->key = $key;
        $this->label = (string)($data['label'] ?? $key);
        $this->category = (string)($data['category'] ?? 'Divers');
        $this->role = (string)($data['role'] ?? '');
        $this->description = (string)($data['description'] ?? '');

        $baseCost = $data['base_cost'] ?? [];
        if (!is_array($baseCost)) {
            throw new InvalidArgumentException(sprintf('Invalid base_cost definition for ship "%s".', $key));
        }

        foreach ($baseCost as $resource => $value) {
            $this->baseCost[$resource] = (int)round((float)$value);
        }

        $this->buildTime = (int)round((float)($data['build_time'] ?? 0));

        $stats = $data['stats'] ?? [];
        if (!is_array($stats)) {
            throw new InvalidArgumentException(sprintf('Invalid stats definition for ship "%s".', $key));
        }

        foreach ($stats as $statKey => $value) {
            $this->stats[$statKey] = (int)round((float)$value);
        }

        $requiresResearch = $data['requires_research'] ?? [];
        if (!is_array($requiresResearch)) {
            throw new InvalidArgumentException(sprintf('Invalid requires_research definition for ship "%s".', $key));
        }

        foreach ($requiresResearch as $researchKey => $level) {
            $this->requiresResearch[$researchKey] = max(0, (int)$level);
        }

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

    public function getRole(): string
    {
        return $this->role;
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

    public function getBuildTime(): int
    {
        return $this->buildTime;
    }

    /** @return array<string, int> */
    public function getStats(): array
    {
        return $this->stats;
    }

    /** @return array<string, int> */
    public function getRequiresResearch(): array
    {
        return $this->requiresResearch;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }
}
