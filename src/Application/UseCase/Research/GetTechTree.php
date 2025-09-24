<?php

declare(strict_types=1);

namespace App\Application\UseCase\Research;

use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ShipCatalog;

class GetTechTree
{
    public function __construct(
        private readonly BuildingStateRepositoryInterface $buildingStates,
        private readonly ResearchStateRepositoryInterface $researchStates,
        private readonly ResearchCatalog $researchCatalog,
        private readonly BuildingCatalog $buildingCatalog,
        private readonly ShipCatalog $shipCatalog
    ) {
    }

    /**
     * @return array{
     *     groups: array<int, array{
     *         key: string,
     *         label: string,
     *         categories: array<int, array{
     *             key: string,
     *             label: string,
     *             items: array<int, array{
     *                 type: string,
     *                 key: string,
     *                 label: string,
     *                 level?: int,
     *                 description?: string,
     *                 image?: ?string,
     *                 requires: array<int, array{type: string, key: string, label: string, required: int, current: int, met: bool}>
     *             }>
     *         }>
     *     }>,
     *     buildingLevels: array<string, int>
     * }
     */
    public function execute(int $planetId): array
    {
        $researchLevels = $this->researchStates->getLevels($planetId);
        $buildingLevels = $this->buildingStates->getLevels($planetId);


        $buildingCategoryLabels = [
            'production' => 'Production',
            'energy' => 'Énergie',
            'research' => 'Recherche',
            'military' => 'Militaire',
            'infrastructure' => 'Infrastructure',
        ];
        $buildingCategoryMap = [
            'metal_mine' => 'production',
            'crystal_mine' => 'production',
            'hydrogen_plant' => 'production',
            'solar_plant' => 'energy',
            'fusion_reactor' => 'energy',
            'antimatter_reactor' => 'energy',
            'research_lab' => 'research',
            'shipyard' => 'military',
            'storage_depot' => 'infrastructure',
            'worker_factory' => 'infrastructure',
            'robot_factory' => 'infrastructure',
        ];

        $buildingsByCategory = [];
        foreach (array_keys($buildingCategoryLabels) as $categoryKey) {
            $buildingsByCategory[$categoryKey] = [];
        }
        foreach ($this->buildingCatalog->all() as $definition) {
            $requirements = [];
            $definitionRequirements = $definition->getRequirements();
            foreach ($definitionRequirements['buildings'] ?? [] as $key => $level) {
                $requiredDefinition = $this->buildingCatalog->get($key);
                $currentLevel = $buildingLevels[$key] ?? 0;
                $requirements[] = [
                    'type' => 'building',
                    'key' => $key,
                    'label' => $requiredDefinition->getLabel(),
                    'required' => (int) $level,
                    'current' => $currentLevel,
                    'met' => $currentLevel >= $level,
                ];
            }
            foreach ($definitionRequirements['research'] ?? [] as $key => $level) {
                $requiredDefinition = $this->researchCatalog->get($key);
                $currentLevel = $researchLevels[$key] ?? 0;
                $requirements[] = [
                    'type' => 'research',
                    'key' => $key,
                    'label' => $requiredDefinition->getLabel(),
                    'required' => (int) $level,
                    'current' => $currentLevel,
                    'met' => $currentLevel >= $level,
                ];
            }

            $item = [
                'type' => 'building',
                'key' => $definition->getKey(),
                'label' => $definition->getLabel(),
                'level' => $buildingLevels[$definition->getKey()] ?? 0,
                'image' => $definition->getImage(),
                'requires' => $requirements,
            ];

            $categoryKey = $buildingCategoryMap[$definition->getKey()] ?? 'infrastructure';
            $buildingsByCategory[$categoryKey][] = $item;
        }

        $buildingCategories = [];
        foreach ($buildingCategoryLabels as $categoryKey => $label) {
            $items = $buildingsByCategory[$categoryKey] ?? [];
            if ($items === []) {
                continue;
            }

            usort($items, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

            $buildingCategories[] = [
                'key' => 'buildings-' . $categoryKey,
                'label' => $label,
                'items' => $items,
            ];
        }


        $shipCategories = [];
        foreach ($this->shipCatalog->groupedByCategory() as $category => $data) {
            $shipItems = [];
            foreach ($data['items'] as $definition) {
                $requirements = [];
                foreach ($definition->getRequiresResearch() as $key => $level) {
                    $requiredDefinition = $this->researchCatalog->get($key);
                    $currentLevel = $researchLevels[$key] ?? 0;
                    $requirements[] = [
                        'type' => 'research',
                        'key' => $key,
                        'label' => $requiredDefinition->getLabel(),
                        'required' => (int) $level,
                        'current' => $currentLevel,
                        'met' => $currentLevel >= $level,
                    ];
                }

                $shipItems[] = [
                    'type' => 'ship',
                    'key' => $definition->getKey(),
                    'label' => $definition->getLabel(),
                    'description' => $definition->getDescription(),
                    'image' => $definition->getImage(),
                    'requires' => $requirements,
                ];
            }

            if ($shipItems === []) {
                continue;
            }

            $shipCategories[] = [
                'key' => 'ship-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($category)),
                'label' => $category,
                'items' => $shipItems,
            ];
        }

        $researchCategories = [];
        $labDefinition = $this->buildingCatalog->get('research_lab');
        foreach ($this->researchCatalog->groupedByCategory() as $category => $data) {
            $items = [];
            foreach ($data['items'] as $definition) {
                $requirements = [];
                foreach ($definition->getRequires() as $key => $level) {
                    $requiredDefinition = $this->researchCatalog->get($key);
                    $currentLevel = $researchLevels[$key] ?? 0;
                    $requirements[] = [
                        'type' => 'research',
                        'key' => $key,
                        'label' => $requiredDefinition->getLabel(),
                        'required' => (int) $level,
                        'current' => $currentLevel,
                        'met' => $currentLevel >= $level,
                    ];
                }

                $labRequirement = $definition->getRequiresLab();
                if ($labRequirement > 0) {
                    $currentLabLevel = $buildingLevels['research_lab'] ?? 0;
                    $requirements[] = [
                        'type' => 'building',
                        'key' => 'research_lab',
                        'label' => $labDefinition->getLabel(),
                        'required' => $labRequirement,
                        'current' => $currentLabLevel,
                        'met' => $currentLabLevel >= $labRequirement,
                    ];
                }

                $items[] = [
                    'type' => 'research',
                    'key' => $definition->getKey(),
                    'label' => $definition->getLabel(),
                    'description' => $definition->getDescription(),
                    'image' => $data['image'],
                    'level' => $researchLevels[$definition->getKey()] ?? 0,
                    'requires' => $requirements,
                ];
            }

            if ($items === []) {
                continue;
            }

            $researchCategories[] = [
                'key' => 'research-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($category)),
                'label' => $category,
                'items' => $items,
            ];
        }

        return [
            'groups' => [
                [
                    'key' => 'buildings',
                    'label' => 'Bâtiments',
                    'categories' => $buildingCategories,
                ],
                [
                    'key' => 'ships',
                    'label' => 'Vaisseaux',
                    'categories' => $shipCategories,
                ],
                [
                    'key' => 'research',
                    'label' => 'Recherches',
                    'categories' => $researchCategories,
                ],
            ],
            'buildingLevels' => $buildingLevels,
        ];
    }
}
