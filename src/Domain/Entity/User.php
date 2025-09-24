<?php

declare(strict_types=1);

namespace App\Domain\Entity;

class User
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly string $username
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
