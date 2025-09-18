<?php

namespace App\Domain\Entity;

class User
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $passwordHash
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
}
