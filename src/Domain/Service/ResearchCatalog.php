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
        }

        $definitions = $config['technologies'] ?? $config;
        if (!is_array($definitions)) {
            $definitions = [];
        }

        foreach ($definitions as $key => $data) {
            if (!is_array($data)) {
                continue;
            }

            $category = (string) ($data['category'] ?? '');
            $image = (string) ($data['image'] ?? '');
            if ($image === '' && $category !== '' && isset($categoryImages[$category])) {
                $image = $categoryImages[$category];
            }
            if ($image === '' && $defaultImage !== '') {
                $image = $defaultImage;
            }

            $definition = new ResearchDefinition(
                $key,
                $data['label'],
                $category,
                $data['description'] ?? '',
                is_array($data['base_cost'] ?? null) ? $data['base_cost'] : [],
                (int) $data['base_time'],
                (float) ($data['growth_cost'] ?? 1.0),
                (float) ($data['growth_time'] ?? 1.0),
                (int) ($data['max_level'] ?? 10),
                is_array($data['requires'] ?? null) ? $data['requires'] : [],
                (int) ($data['requires_lab'] ?? 0),
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
