<?php

namespace App\Infrastructure\Config;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class BalanceConfigLoader
{
    /**
     * @var array<string, string>
     */
    private array $files;

    /**
     * @param array<string, string> $files
     */
    public function __construct(
        private readonly string $basePath,
        array $files = [
            'buildings' => 'buildings.yaml',
            'research' => 'research.yaml',
            'ships' => 'ships.yaml',
        ]
    ) {
        if (!is_dir($basePath)) {
            throw new InvalidArgumentException(sprintf('Balance configuration directory "%s" does not exist.', $basePath));
        }

        $this->files = $files;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadBuildings(): array
    {
        /** @var array<string, array<string, mixed>> $config */
        $config = $this->load('buildings');

        return $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadResearch(): array
    {
        /** @var array<string, array<string, mixed>> $config */
        $config = $this->load('research');

        return $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadShips(): array
    {
        /** @var array<string, array<string, mixed>> $config */
        $config = $this->load('ships');

        return $config;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function loadAll(): array
    {
        $result = [];

        foreach (array_keys($this->files) as $section) {
            $result[$section] = $this->load($section);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $section): array
    {
        $file = $this->files[$section] ?? null;
        if ($file === null) {
            throw new InvalidArgumentException(sprintf('Unknown balance configuration section "%s".', $section));
        }

        $path = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Balance configuration file "%s" was not found.', $path));
        }

        try {
            $parsed = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_CONSTANT);
        } catch (ParseException $exception) {
            throw new RuntimeException(sprintf('Unable to parse balance configuration file "%s".', $path), 0, $exception);
        }

        if ($parsed === null) {
            return [];
        }

        if (!is_array($parsed)) {
            throw new RuntimeException(sprintf('Balance configuration file "%s" must define an array structure.', $path));
        }

        return $this->normalize($parsed);
    }

    /**
     * @param array<string|int, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function normalize(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalize($value);

                continue;
            }

            if (is_int($value) || is_float($value) || is_string($value) || is_bool($value) || $value === null) {
                $normalized[$key] = $value;

                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
