<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Session;

interface SessionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    public function has(string $key): bool;

    public function invalidate(): void;

    public function flash(string $key, mixed $value): void;

    public function pull(string $key, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
