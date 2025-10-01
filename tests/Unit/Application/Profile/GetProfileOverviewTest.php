<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Profile;

use App\Application\UseCase\Dashboard\GetDashboard;
use App\Application\UseCase\Profile\GetProfileOverview;
use App\Domain\Entity\Planet;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GetProfileOverviewTest extends TestCase
{
    public function testThrowsWhenUserMissing(): void
    {
        $useCase = new GetProfileOverview(
            new class() implements UserRepositoryInterface {
                public function findByEmail(string $email): ?User
                {
                    return null;
                }

                public function find(int $id): ?User
                {
                    return null;
                }

                public function save(string $email, string $passwordHash, ?string $username = null): User
                {
                    throw new RuntimeException('Not implemented.');
                }
            },
            new class() extends GetDashboard {
                public function __construct()
                {
                }

                public function execute(int $userId): array
                {
                    return [];
                }
            }
        );

        $this->expectException(RuntimeException::class);
        $useCase->execute(10);
    }

    public function testBuildsOverviewForUser(): void
    {
        $planet = $this->createPlanet();
        $dashboard = [
            'planets' => [[
                'planet' => $planet,
                'production' => [
                    'metal' => 100,
                    'crystal' => 50,
                    'hydrogen' => 25,
                    'energy' => 75,
                ],
                'levels' => [
                    'research_lab' => 2,
                    'shipyard' => 1,
                ],
            ]],
        ];

        $useCase = new GetProfileOverview(
            new class() implements UserRepositoryInterface {
                public function findByEmail(string $email): ?User
                {
                    return null;
                }

                public function find(int $id): ?User
                {
                    return new User($id, 'user@example.com', 'hash', 'Pilot');
                }

                public function save(string $email, string $passwordHash, ?string $username = null): User
                {
                    throw new RuntimeException('Not implemented.');
                }
            },
            new class($dashboard) extends GetDashboard {
                public function __construct(private array $dashboard)
                {
                }

                public function execute(int $userId): array
                {
                    return $this->dashboard;
                }
            }
        );

        $result = $useCase->execute(5);

        self::assertSame('user@example.com', $result['account']['email']);
        self::assertSame('Pilot', $result['account']['username']);
        self::assertSame($planet, $result['planets'][0]);
        self::assertSame($planet->getId(), $result['selectedPlanetId']);
        self::assertSame(100, $result['activePlanetSummary']['resources']['metal']['perHour']);
        self::assertTrue($result['facilityStatuses']['research_lab']);
        self::assertTrue($result['facilityStatuses']['shipyard']);
    }

    private function createPlanet(): Planet
    {
        return new Planet(
            3,
            5,
            1,
            1,
            1,
            'Hestia',
            5000,
            -10,
            30,
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
            new DateTimeImmutable('-10 minutes')
        );
    }
}
