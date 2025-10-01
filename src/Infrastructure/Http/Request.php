<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Http\Session\Session;

class Request
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array  $query,
        private readonly array  $body,
        public readonly Session $session,
        private readonly array  $headers = []
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

            $storage = &$_SESSION;
            $session = new Session($storage);
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$headerName] = (string)$value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $serverKey => $headerName) {
            if (isset($_SERVER[$serverKey]) && is_scalar($_SERVER[$serverKey])) {
                $headers[$headerName] = (string)$_SERVER[$serverKey];
            }
        }

        $body = $_POST;
        $contentType = strtolower($headers['Content-Type'] ?? '');
        if ($body === [] && str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw !== false && $raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        /** @var array<string, mixed> $decoded */
                        $body = $decoded;
                    }
                } catch (\JsonException) {
                    $body = [];
                }
            }
        }

        return new self(
            strtoupper($method),
            $path,
            $_GET,
            $body,
            $session,
            $headers
        );
    }

    public function withSession(Session $session): self
    {
        $clone = new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $session,
            $this->headers
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

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function wantsJson(): bool
    {
        $accept = strtolower($this->getHeader('Accept') ?? '');
        if ($accept !== '' && str_contains($accept, 'application/json')) {
            return true;
        }

        $requestedWith = strtolower($this->getHeader('X-Requested-With') ?? '');

        return $requestedWith === 'xmlhttprequest';
    }

    public function getHeader(string $name): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $headerName => $value) {
            if (strtolower($headerName) === $normalized) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
