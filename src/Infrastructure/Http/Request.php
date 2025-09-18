<?php

namespace App\Infrastructure\Http;

use App\Infrastructure\Http\Session\Session;

class Request
{
    private array $attributes = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        public readonly Session $session
    ) {
    }

    public static function fromGlobals(?Session $session = null): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($session === null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $storage =& $_SESSION;
            $session = new Session($storage);
        }

        return new self(
            strtoupper($method),
            $path,
            $_GET,
            $_POST,
            $session
        );
    }

    public function withSession(Session $session): self
    {
        $clone = new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $session
        );
        $clone->attributes = $this->attributes;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->query;
    }

    public function getBodyParams(): array
    {
        return $this->body;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $this->body[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
