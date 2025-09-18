<?php

namespace App\Domain\Service;

use App\Domain\Entity\ShipDefinition;
use InvalidArgumentException;

class ShipCatalog
{
    /** @var array<string, ShipDefinition> */
    private array $definitions = [];

    /** @var array<string, array{image: string, items: ShipDefinition[]}> */
    private array $categories = [];

    /**
     * @param array<string, array<string, mixed>> $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $key => $data) {
            $definition = new ShipDefinition(
                $key,
                $data['label'],
                $data['category'] ?? 'Divers',
                $data['role'] ?? '',
                $data['description'] ?? '',
                $data['base_cost'] ?? [],
                (int) ($data['build_time'] ?? 0),
                $data['stats'] ?? [],
                $data['requires_research'] ?? [],
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

    /** @return ShipDefinition[] */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function get(string $key): ShipDefinition
    {
        if (!isset($this->definitions[$key])) {
            throw new InvalidArgumentException(sprintf('Vaisseau "%s" inconnu.', $key));
        }

        return $this->definitions[$key];
    }

    /**
     * @return array<string, array{image: string, items: ShipDefinition[]}>
     */
    public function groupedByCategory(): array
    {
        return $this->categories;
    }
}
