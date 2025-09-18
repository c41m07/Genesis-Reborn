<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use PDO;

class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower($email)]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new User((int) $row['id'], $row['email'], $row['password']);
    }

    public function find(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new User((int) $row['id'], $row['email'], $row['password']);
    }

    public function save(string $email, string $passwordHash): User
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
        $stmt->execute([
            'email' => strtolower($email),
            'password' => $passwordHash,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return new User($id, strtolower($email), $passwordHash);
    }
}
