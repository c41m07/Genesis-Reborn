<?php

namespace App\Domain\Service;

use App\Domain\Entity\BuildingDefinition;
use InvalidArgumentException;

class BuildingCatalog
{
    /** @var array<string, BuildingDefinition> */
    private array $definitions = [];

    /**
     * @param array<string, array<string, mixed>> $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $key => $data) {
            $this->definitions[$key] = new BuildingDefinition(
                $key,
                $data['label'],
                $data['base_cost'],
                (float) $data['growth_cost'],
                (int) $data['base_time'],
                (float) $data['growth_time'],
                (int) ($data['prod_base'] ?? 0),
                (float) ($data['prod_growth'] ?? 1.0),
                (int) ($data['energy_use_base'] ?? 0),
                (float) ($data['energy_use_growth'] ?? 1.0),
                (bool) ($data['energy_use_linear'] ?? false),
                $data['affects'],
                $data['requires'] ?? [],
                $data['image'] ?? null,
                $data['ship_build_speed_bonus'] ?? [],
                $data['storage'] ?? [],
                $data['upkeep'] ?? []
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
