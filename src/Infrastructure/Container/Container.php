<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

use InvalidArgumentException;

class Container
{
    /**
     * @var array<string, mixed>
     */
    private array $parameters;

    /** @var array<string, callable(self): mixed> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $services = [];

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function set(string $id, callable $factory): void
    {
        $this->definitions[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        if (!isset($this->definitions[$id])) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not defined.', $id));
        }

        $this->services[$id] = $this->definitions[$id]($this);

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || array_key_exists($id, $this->services);
    }


    public function getParameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $default;
    }

    public function setParameter(string $name, mixed $value): void
    {
        $this->parameters[$name] = $value;
    }


}
