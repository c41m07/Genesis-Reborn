<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

class Response
{
    /**
     * @var array<string, string>
     */
    private array $headers = [];

    public function __construct(
        private string $content = '',
        private int    $statusCode = 200
    ) {
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->content;
    }
}
