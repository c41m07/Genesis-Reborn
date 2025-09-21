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
        $categoryImages = [];
        if (isset($config['category_images']) && is_array($config['category_images'])) {
            foreach ($config['category_images'] as $category => $image) {
                if (is_string($image)) {
                    $categoryImages[$category] = $image;
                }
            }
        }

        $defaultImage = '';
        if (isset($config['default_image']) && is_string($config['default_image'])) {
            $defaultImage = $config['default_image'];
        } elseif (isset($categoryImages['Divers'])) {
            $defaultImage = $categoryImages['Divers'];
        }

        $shipConfig = $config['ships'] ?? $config;
        if (!is_array($shipConfig)) {
            $shipConfig = [];
        }

        foreach ($shipConfig as $key => $data) {
            if (!is_array($data)) {
                continue;
            }

            $category = (string) ($data['category'] ?? 'Divers');
            $image = (string) ($data['image'] ?? '');
            if ($image === '' && isset($categoryImages[$category])) {
                $image = $categoryImages[$category];
            }
            if ($image === '' && $defaultImage !== '') {
                $image = $defaultImage;
            }

            $definition = new ShipDefinition(
                $key,
                $data['label'],
                $category,
                $data['role'] ?? '',
                $data['description'] ?? '',
                is_array($data['base_cost'] ?? null) ? $data['base_cost'] : [],
                (int) ($data['build_time'] ?? 0),
                is_array($data['stats'] ?? null) ? $data['stats'] : [],
                is_array($data['requires_research'] ?? null) ? $data['requires_research'] : [],
                $image
            );

            $this->definitions[$key] = $definition;
            if (!isset($this->categories[$category])) {
                $categoryImage = $categoryImages[$category] ?? $image;
                if ($categoryImage === '' && $defaultImage !== '') {
                    $categoryImage = $defaultImage;
                }

                $this->categories[$category] = [
                    'image' => (string) $categoryImage,
                    'items' => [],
                ];
            } elseif ($this->categories[$category]['image'] === '' && $image !== '') {
                $this->categories[$category]['image'] = $image;
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
