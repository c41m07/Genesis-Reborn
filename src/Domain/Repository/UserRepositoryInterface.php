<?php

namespace App\Domain\Repository;

use App\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function find(int $id): ?User;

    public function save(string $email, string $passwordHash): User;
}
