<?php

namespace App\Domain\Service;

use App\Domain\Entity\ShipDefinition;
use App\Infrastructure\Config\ShipConfig;
use InvalidArgumentException;

class ShipCatalog
{
    /** @var array<string, ShipDefinition> */
    private array $definitions = [];

    /** @var array<string, array{image: string, items: ShipDefinition[]}> */
    private array $categories = [];

    /**
     * @param iterable<ShipConfig> $configs
     */
    public function __construct(iterable $configs)
    {
        foreach ($configs as $config) {
            if (!$config instanceof ShipConfig) {
                throw new InvalidArgumentException('ShipCatalog expects instances of ShipConfig.');
            }

            $key = $config->getKey();

            $definition = new ShipDefinition(
                $key,
                $config->getLabel(),
                $config->getCategory(),
                $config->getRole(),
                $config->getDescription(),
                $config->getBaseCost(),
                $config->getBuildTime(),
                $config->getStats(),
                $config->getRequiresResearch(),
                $config->getImage() ?? ''
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
