<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Session;

/**
 * Simple mutable session wrapper storing key/value data.
 */
class Session implements SessionInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array &$data)
    {
        $this->data = & $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function invalidate(): void
    {
        $this->data = [];
    }

    public function flash(string $key, mixed $value): void
    {
        if (!isset($this->data['_flashes'][$key]) || !is_array($this->data['_flashes'][$key])) {
            $this->data['_flashes'][$key] = [];
        }

        $this->data['_flashes'][$key][] = $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        $value = $this->data[$key];
        unset($this->data[$key]);

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
