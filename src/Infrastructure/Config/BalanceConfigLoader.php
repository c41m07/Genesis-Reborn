<?php

namespace App\Infrastructure\Config;

use RuntimeException;

class BalanceConfigLoader
{
    private ?array $cache = null;

    public function __construct(private readonly string $configPath)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->parseFile($this->configPath);
        }

        return $this->cache;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $value = $this->all();

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function reset(): void
    {
        $this->cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Balance configuration file "%s" was not found.', $path));
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException(sprintf('Unable to read balance configuration file "%s".', $path));
        }

        return $this->parseLines($lines);
    }

    /**
     * @param list<string> $lines
     * @return array<string, mixed>
     */
    private function parseLines(array $lines): array
    {
        $root = [];
        $stack = [
            [
                'indent' => -1,
                'type' => 'map',
                'container' => &$root,
            ],
        ];

        foreach ($lines as $rawLine) {
            $line = rtrim($rawLine);
            if ($line === '') {
                continue;
            }

            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $indent = strlen($line) - strlen($trimmed);

            while (count($stack) > 1 && $indent <= $stack[array_key_last($stack)]['indent']) {
                array_pop($stack);
            }

            $currentIndex = array_key_last($stack);
            $current = &$stack[$currentIndex];

            if ($current['type'] === 'pending') {
                if (str_starts_with($trimmed, '- ')) {
                    $current['type'] = 'seq';
                    $current['container'] = [];
                } else {
                    $current['type'] = 'map';
                    $current['container'] = [];
                }
            }

            if (str_starts_with($trimmed, '- ')) {
                if ($current['type'] !== 'seq') {
                    $current['type'] = 'seq';
                    if (!is_array($current['container'])) {
                        $current['container'] = [];
                    }
                }

                $valueString = substr($trimmed, 2);
                if ($valueString === '') {
                    $current['container'][] = [];
                    $index = array_key_last($current['container']);
                    $stack[] = [
                        'indent' => $indent,
                        'type' => 'pending',
                        'container' => &$current['container'][$index],
                    ];
                } else {
                    $current['container'][] = $this->parseScalar($valueString);
                }

                continue;
            }

            if (!str_contains($trimmed, ':')) {
                throw new RuntimeException(sprintf('Invalid balance configuration line: "%s".', $rawLine));
            }

            [$key, $valuePart] = explode(':', $trimmed, 2);
            $key = trim($key);
            $valuePart = ltrim($valuePart, ' ');

            if ($current['type'] !== 'map') {
                $current['type'] = 'map';
                if (!is_array($current['container'])) {
                    $current['container'] = [];
                }
            }

            if ($valuePart === '') {
                $current['container'][$key] = [];
                $stack[] = [
                    'indent' => $indent,
                    'type' => 'pending',
                    'container' => &$current['container'][$key],
                ];

                continue;
            }

            $current['container'][$key] = $this->parseScalar($valuePart);
        }

        return $root;
    }

    private function parseScalar(string $value): mixed
    {
        $lower = strtolower($value);
        if ($lower === 'null' || $value === '~') {
            return null;
        }

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
