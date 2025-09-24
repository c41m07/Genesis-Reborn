<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\BuildingDefinition;
use App\Infrastructure\Config\BuildingConfig;
use InvalidArgumentException;

class BuildingCatalog
{
    /** @var array<string, BuildingDefinition> */
    private array $definitions = [];

    /**
     * @param iterable<BuildingConfig> $configs
     */
    public function __construct(iterable $configs)
    {
        foreach ($configs as $config) {
            if (!$config instanceof BuildingConfig) {
                throw new InvalidArgumentException('BuildingCatalog expects instances of BuildingConfig.');
            }

            $key = $config->getKey();

            $this->definitions[$key] = new BuildingDefinition(
                $key,
                $config->getLabel(),
                $config->getBaseCost(),
                $config->getGrowthCost(),
                $config->getBaseTime(),
                $config->getGrowthTime(),
                $config->getProductionBase(),
                $config->getProductionGrowth(),
                $config->getEnergyUseBase(),
                $config->getEnergyUseGrowth(),
                $config->isEnergyUseLinear(),
                $config->getAffects(),
                $config->getRequirements(),
                $config->getImage(),
                $config->getShipBuildSpeedBonus(),
                $config->getResearchSpeedBonus(),
                $config->getStorage(),
                $config->getUpkeep(),
                $config->getConstructionSpeedBonus()
            );
        }
    }

    /** @return BuildingDefinition[] */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function get(string $key): BuildingDefinition
    {
        if (!isset($this->definitions[$key])) {
            throw new InvalidArgumentException(sprintf('Bâtiment "%s" inconnu.', $key));
        }

        return $this->definitions[$key];
    }
}
