<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Auth;

use App\Application\UseCase\Auth\RegisterUser;
use App\Domain\Entity\Planet;
use App\Domain\Entity\User;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Http\Session\Session;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RegisterUserTest extends TestCase
{
    public function testCreatesHomeworldWithStartingResources(): void
    {
        $userRepository = new InMemoryUserRepository();
        $planetRepository = new InMemoryPlanetRepository();
        $storage = [];
        $session = new Session($storage);

        $useCase = new RegisterUser(
            $userRepository,
            $planetRepository,
            $session
        );

        $result = $useCase->execute('Player@example.com', 'password123', 'password123');

        self::assertTrue($result['success']);
        $userId = $session->get('user_id');
        self::assertIsInt($userId);

        $planets = $planetRepository->findByUser($userId);
        self::assertCount(1, $planets);
        $planet = $planets[0];

        self::assertSame(1000, $planet->getMetal());
        self::assertSame(1000, $planet->getCrystal());
        self::assertSame(1000, $planet->getHydrogen());
        self::assertSame(0, $planet->getEnergy());
        self::assertSame(1000, $planet->getMetalCapacity());
        self::assertSame(1000, $planet->getCrystalCapacity());
        self::assertSame(1000, $planet->getHydrogenCapacity());
        self::assertSame(1000, $planet->getEnergyCapacity());
        self::assertGreaterThanOrEqual(1, $planet->getGalaxy());
        self::assertLessThanOrEqual(9, $planet->getGalaxy());
        self::assertGreaterThanOrEqual(1, $planet->getSystem());
        self::assertLessThanOrEqual(9, $planet->getSystem());
        self::assertGreaterThanOrEqual(1, $planet->getPosition());
        self::assertLessThanOrEqual(9, $planet->getPosition());
    }
}

/**
 * @implements UserRepositoryInterface
 */
class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $users = [];

    private int $autoIncrement = 1;

    public function findByEmail(string $email): ?User
    {
        $email = strtolower($email);
        foreach ($this->users as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        return null;
    }

    public function find(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function save(string $email, string $passwordHash, ?string $username = null): User
    {
        $id = $this->autoIncrement++;
        $email = strtolower($email);
        $username = $username ?? preg_replace('/[^a-z0-9]+/i', '-', strstr($email, '@', true) ?: $email) ?? 'commandant';
        $username = trim(strtolower($username), '-');
        if ($username === '') {
            $username = 'commandant';
        }

        $user = new User($id, $email, $passwordHash, $username);
        $this->users[$user->getId()] = $user;

        return $user;
    }
}

/**
 * @implements PlanetRepositoryInterface
 */
class InMemoryPlanetRepository implements PlanetRepositoryInterface
{
    /** @var array<int, Planet> */
    private array $planets = [];

    private int $autoIncrement = 1;

    public function findByUser(int $userId): array
    {
        return array_values(array_filter(
            $this->planets,
            static fn (Planet $planet): bool => $planet->getUserId() === $userId
        ));
    }

    public function find(int $id): ?Planet
    {
        return $this->planets[$id] ?? null;
    }

    public function createHomeworld(int $userId): Planet
    {
        $planet = new Planet(
            $this->autoIncrement++,
            $userId,
            random_int(1, 9),
            random_int(1, 9),
            random_int(1, 9),
            'Planète mère',
            12000,
            -20,
            40,
            1000,
            1000,
            1000,
            0,
            0,
            0,
            0,
            0,
            1000,
            1000,
            1000,
            1000,
            new DateTimeImmutable()
        );
        $this->planets[$planet->getId()] = $planet;

        return $planet;
    }

    public function update(Planet $planet): void
    {
        $this->planets[$planet->getId()] = $planet;
    }

    public function rename(int $planetId, string $name): void
    {
        if (!isset($this->planets[$planetId])) {
            return;
        }

        $this->planets[$planetId]->rename($name);
    }
}
