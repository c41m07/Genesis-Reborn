<?php

declare(strict_types=1);

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
        $stmt = $this->pdo->prepare('SELECT id, email, username, password_hash FROM players WHERE email = :email');
        $stmt->execute(['email' => strtolower($email)]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new User((int) $row['id'], $row['email'], $row['password_hash'], $row['username']);
    }

    public function find(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, email, username, password_hash FROM players WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new User((int) $row['id'], $row['email'], $row['password_hash'], $row['username']);
    }

    public function save(string $email, string $passwordHash, ?string $username = null): User
    {
        $email = strtolower($email);
        $baseUsername = $username ?? $this->deriveUsernameFromEmail($email);
        $normalized = $this->normalizeUsername($baseUsername);
        $finalUsername = $this->ensureUniqueUsername($normalized);

        $stmt = $this->pdo->prepare('INSERT INTO players (email, username, password_hash, created_at, updated_at)
            VALUES (:email, :username, :password, NOW(), NOW())');
        $stmt->execute([
            'email' => $email,
            'username' => $finalUsername,
            'password' => $passwordHash,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return new User($id, $email, $passwordHash, $finalUsername);
    }

    private function deriveUsernameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true);

        return $local !== false ? $local : $email;
    }

    private function normalizeUsername(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            $value = 'commandant';
        }

        return substr($value, 0, 60);
    }

    private function ensureUniqueUsername(string $base): string
    {
        $candidate = $base;
        $suffix = 1;
        $maxLength = 60;

        while ($this->usernameExists($candidate)) {
            $suffixString = (string) $suffix;
            $availableLength = max(1, $maxLength - strlen($suffixString));
            $candidate = substr($base, 0, $availableLength) . $suffixString;
            ++$suffix;
        }

        return $candidate;
    }

    private function usernameExists(string $username): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM players WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);

        return (bool) $stmt->fetchColumn();
    }
}
