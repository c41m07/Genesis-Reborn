<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Galaxy;

use App\Application\UseCase\Galaxy\GetGalaxyOverview;
use App\Domain\Entity\Planet;
use App\Domain\Entity\User;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetGalaxyOverviewTest extends TestCase
{
    public function testReturnsEmptyStateWhenUserHasNoPlanets(): void
    {
        $useCase = new GetGalaxyOverview(
            new class () implements PlanetRepositoryInterface {
                public function findByUser(int $userId): array
                {
                    return [];
                }

                public function find(int $id): ?Planet
                {
                    return null;
                }

                public function findByCoordinates(int $galaxy, int $system): array
                {
                    return [];
                }

                public function createHomeworld(int $userId): Planet
                {
                    throw new \RuntimeException('Not implemented.');
                }

                public function update(Planet $planet): void
                {
                }

                public function rename(int $planetId, string $name): void
                {
                }
            },
            new class () implements BuildingStateRepositoryInterface {
                public function getLevels(int $planetId): array
                {
                    return [];
                }

                public function setLevel(int $planetId, string $buildingKey, int $level): void
                {
                }
            },
            new class () implements UserRepositoryInterface {
                public function findByEmail(string $email): ?User
                {
                    return null;
                }

                public function find(int $id): ?User
                {
                    return new User($id, 'user@example.com', 'hash', 'Commander');
                }

                public function save(string $email, string $passwordHash, ?string $username = null): User
                {
                    throw new \RuntimeException('Not implemented.');
                }
            }
        );

        $result = $useCase->execute(42, ['galaxy' => 2, 'system' => 3, 'view' => 'colonizable']);

        self::assertSame([], $result['planets']);
        self::assertNull($result['selectedPlanetId']);
        self::assertSame(2, $result['filters']['galaxy']);
        self::assertSame('colonizable', $result['filters']['view']);
        self::assertNotEmpty($result['messages']);
        self::assertSame('info', $result['messages'][0]['type']);
    }

    public function testBuildsOverviewForOwnedPlanets(): void
    {
        $planetA = $this->createPlanet(1, 42, 'Gaia', 1, 1, 1);
        $planetB = $this->createPlanet(2, 99, 'Nemesis', 1, 1, 2);

        $useCase = new GetGalaxyOverview(
            new class ($planetA, $planetB) implements PlanetRepositoryInterface {
                public function __construct(private Planet $owned, private Planet $other)
                {
                }

                public function findByUser(int $userId): array
                {
                    return [$this->owned];
                }

                public function find(int $id): ?Planet
                {
                    if ($this->owned->getId() === $id) {
                        return $this->owned;
                    }

                    return $this->other->getId() === $id ? $this->other : null;
                }

                public function findByCoordinates(int $galaxy, int $system): array
                {
                    return [$this->owned, $this->other];
                }

                public function createHomeworld(int $userId): Planet
                {
                    throw new \RuntimeException('Not implemented.');
                }

                public function update(Planet $planet): void
                {
                }

                public function rename(int $planetId, string $name): void
                {
                }
            },
            new class () implements BuildingStateRepositoryInterface {
                public function getLevels(int $planetId): array
                {
                    return $planetId === 1 ? ['research_lab' => 2, 'shipyard' => 0] : [];
                }

                public function setLevel(int $planetId, string $buildingKey, int $level): void
                {
                }
            },
            new class ($planetA, $planetB) implements UserRepositoryInterface {
                public function __construct(private Planet $owned, private Planet $other)
                {
                }

                public function findByEmail(string $email): ?User
                {
                    return null;
                }

                public function find(int $id): ?User
                {
                    if ($id === $this->owned->getUserId()) {
                        return new User($id, 'owned@example.com', 'hash', 'Owner');
                    }

                    if ($id === $this->other->getUserId()) {
                        return new User($id, 'other@example.com', 'hash', 'Rival');
                    }

                    return null;
                }

                public function save(string $email, string $passwordHash, ?string $username = null): User
                {
                    throw new \RuntimeException('Not implemented.');
                }
            }
        );

        $result = $useCase->execute(42, ['planet' => 1, 'view' => 'inactive', 'q' => 'Gaia']);

        self::assertSame(1, $result['selectedPlanetId']);
        self::assertSame('inactive', $result['filters']['view']);
        self::assertSame(['research_lab' => true, 'shipyard' => false], $result['facilityStatuses']);
        self::assertNotNull($result['activePlanetSummary']);
        self::assertCount(2, $result['players']);
        self::assertSame([], $result['messages']);
    }

    private function createPlanet(int $id, int $userId, string $name, int $galaxy, int $system, int $position): Planet
    {
        return new Planet(
            $id,
            $userId,
            $galaxy,
            $system,
            $position,
            $name,
            5000,
            -20,
            40,
            1000,
            500,
            250,
            100,
            2000,
            1500,
            1000,
            500,
            10000,
            10000,
            10000,
            10000,
            new DateTimeImmutable('-1 hour')
        );
    }
}
