<?php

namespace App\Infrastructure\Http;

class Route
{
    /** @param array<string,string> $parameters */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $handler,
        private readonly array $parameters = []
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** @return array{0: string, 1: string} */
    public function getHandler(): array
    {
        return $this->handler;
    }

    /** @return array<string,string> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
