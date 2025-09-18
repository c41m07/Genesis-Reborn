<?php

namespace App\Domain\Service;

use App\Domain\Entity\ResearchDefinition;
use InvalidArgumentException;

class ResearchCatalog
{
    /** @var array<string, ResearchDefinition> */
    private array $definitions = [];

    /** @var array<string, array{image: string, items: ResearchDefinition[]}> */
    private array $categories = [];

    /**
     * @param array<string, array<string, mixed>> $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $key => $data) {
            $definition = new ResearchDefinition(
                $key,
                $data['label'],
                $data['category'],
                $data['description'] ?? '',
                $data['base_cost'],
                (int) $data['base_time'],
                (float) ($data['growth_cost'] ?? 1.0),
                (float) ($data['growth_time'] ?? 1.0),
                (int) ($data['max_level'] ?? 10),
                $data['requires'] ?? [],
                (int) ($data['requires_lab'] ?? 0),
                $data['image'] ?? ''
            );

            $this->definitions[$key] = $definition;
            $category = $definition->getCategory();

            if (!isset($this->categories[$category])) {
                $this->categories[$category] = [
                    'image' => $definition->getImage(),
                    'items' => [],
                ];
            }

            $this->categories[$category]['items'][] = $definition;
        }
    }

    /** @return ResearchDefinition[] */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function get(string $key): ResearchDefinition
    {
        if (!isset($this->definitions[$key])) {
            throw new InvalidArgumentException(sprintf('Recherche "%s" inconnue.', $key));
        }

        return $this->definitions[$key];
    }

    /**
     * @return array<string, array{image: string, items: ResearchDefinition[]}>
     */
    public function groupedByCategory(): array
    {
        return $this->categories;
    }
}
