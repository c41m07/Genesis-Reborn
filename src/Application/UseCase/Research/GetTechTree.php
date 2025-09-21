<?php

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
     *     categories: array<int, array{
     *         key: string,
     *         label: string,
     *         items: array<int, array{
     *             type: string,
     *             key: string,
     *             label: string,
     *             level?: int,
     *             description?: string,
     *             image?: ?string,
     *             requires: array<int, array{type: string, key: string, label: string, required: int, current: int, met: bool}>
     *         }>
     *     }>
     * }
     */
    public function execute(int $planetId): array
    {
        $researchLevels = $this->researchStates->getLevels($planetId);
        $buildingLevels = $this->buildingStates->getLevels($planetId);

        $categories = [];

        $buildingItems = [];
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

            $buildingItems[] = [
                'type' => 'building',
                'key' => $definition->getKey(),
                'label' => $definition->getLabel(),
                'level' => $buildingLevels[$definition->getKey()] ?? 0,
                'image' => $definition->getImage(),
                'requires' => $requirements,
            ];
        }
        usort($buildingItems, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
        $categories[] = [
            'key' => 'buildings',
            'label' => 'Bâtiments',
            'items' => $buildingItems,
        ];

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

            $categories[] = [
                'key' => 'ship-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($category)),
                'label' => $category,
                'items' => $shipItems,
            ];
        }

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

            $categories[] = [
                'key' => 'research-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($category)),
                'label' => $category,
                'items' => $items,
            ];
        }

        return [
            'categories' => $categories,
            'buildingLevels' => $buildingLevels,
        ];
    }
}
