<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\ResearchDefinition;
use App\Infrastructure\Config\TechnologyConfig;
use InvalidArgumentException;

class ResearchCatalog
{
    /** @var array<string, ResearchDefinition> */
    private array $definitions = [];

    /** @var array<string, array{image: string, items: ResearchDefinition[]}> */
    private array $categories = [];

    /**
     * @param iterable<TechnologyConfig> $configs
     */
    public function __construct(iterable $configs)
    {
        foreach ($configs as $config) {
            if (!$config instanceof TechnologyConfig) {
                throw new InvalidArgumentException('ResearchCatalog expects instances of TechnologyConfig.');
            }

            $key = $config->getKey();

            $definition = new ResearchDefinition(
                $key,
                $config->getLabel(),
                $config->getCategory(),
                $config->getDescription(),
                $config->getBaseCost(),
                $config->getBaseTime(),
                $config->getGrowthCost(),
                $config->getGrowthTime(),
                $config->getMaxLevel(),
                $config->getRequires(),
                $config->getRequiresLab(),
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
