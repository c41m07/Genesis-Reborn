<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Auth;

use App\Application\UseCase\Auth\RegisterUser;
use App\Domain\Entity\Planet;
use App\Domain\Entity\User;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use App\Infrastructure\Http\Session\Session;
use PHPUnit\Framework\TestCase;

class RegisterUserTest extends TestCase
{
    public function testCreatesHomeworldWithStartingResources(): void
    {
        $userRepository = new InMemoryUserRepository();
        $planetRepository = new InMemoryPlanetRepository();
        $buildingStates = new InMemoryBuildingStateRepository();
        $config = require dirname(__DIR__, 4) . '/config/game/buildings.php';
        $catalog = new BuildingCatalog($config);
        $calculator = new BuildingCalculator();
        $storage = [];
        $session = new Session($storage);

        $useCase = new RegisterUser(
            $userRepository,
            $planetRepository,
            $buildingStates,
            $catalog,
            $calculator,
            $session
        );

        $result = $useCase->execute('Player@example.com', 'password123', 'password123');

        self::assertTrue($result['success']);
        $userId = $session->get('user_id');
        self::assertIsInt($userId);

        $planets = $planetRepository->findByUser($userId);
        self::assertCount(1, $planets);
        $planet = $planets[0];

        self::assertSame(10000, $planet->getMetal());
        self::assertSame(10000, $planet->getCrystal());
        self::assertSame(10000, $planet->getEnergy());
        self::assertSame(0, $planet->getHydrogen());

        $levels = $buildingStates->getLevels($planet->getId());
        self::assertSame(1, $levels['metal_mine'] ?? 0);
        self::assertSame(1, $levels['crystal_mine'] ?? 0);
        self::assertSame(1, $levels['solar_plant'] ?? 0);

        $expectedMetal = $calculator->productionAt($catalog->get('metal_mine'), 1);
        $expectedCrystal = $calculator->productionAt($catalog->get('crystal_mine'), 1);
        $expectedHydrogen = 0;
        $expectedEnergy = $calculator->productionAt($catalog->get('solar_plant'), 1);
        $expectedEnergyConsumption = $calculator->energyUseAt($catalog->get('metal_mine'), 1)
            + $calculator->energyUseAt($catalog->get('crystal_mine'), 1)
            + $calculator->energyUseAt($catalog->get('solar_plant'), 1);

        self::assertSame($expectedMetal, $planet->getMetalPerHour());
        self::assertSame($expectedCrystal, $planet->getCrystalPerHour());
        self::assertSame($expectedHydrogen, $planet->getHydrogenPerHour());
        self::assertSame($expectedEnergy - $expectedEnergyConsumption, $planet->getEnergyPerHour());
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

    public function save(string $email, string $passwordHash): User
    {
        $user = new User($this->autoIncrement++, strtolower($email), $passwordHash);
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
            'Planète mère',
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0
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

/**
 * @implements BuildingStateRepositoryInterface
 */
class InMemoryBuildingStateRepository implements BuildingStateRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $levels = [];

    public function getLevels(int $planetId): array
    {
        return $this->levels[$planetId] ?? [];
    }

    public function setLevel(int $planetId, string $buildingKey, int $level): void
    {
        $this->levels[$planetId][$buildingKey] = $level;
    }
}
