<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fleet;

use App\Application\UseCase\Fleet\MergeIdleFleets;
use App\Application\UseCase\Fleet\RenameIdleFleet;
use App\Domain\Entity\Planet;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HangarManagementTest extends TestCase
{
    public function testRenameFleetSuccessfully(): void
    {
        $planet = $this->createPlanet(1, 42);
        $planets = new HangarPlanetRepository([$planet]);
        $fleets = new HangarFleetRepository();
        $fleetId = $fleets->createFleet(42, 1, 'Escadre Alpha');

        $useCase = new RenameIdleFleet($planets, $fleets);
        $result = $useCase->execute(42, 1, $fleetId, 'Escadre Renommée');

        self::assertTrue($result['success']);
        self::assertSame('La flotte "Escadre Renommée" a été renommée avec succès.', $result['message']);

        $fleet = $fleets->findIdleFleet($fleetId);
        self::assertNotNull($fleet);
        self::assertSame('Escadre Renommée', $fleet['name']);
    }

    public function testRenameFleetRejectsGarrison(): void
    {
        $planet = $this->createPlanet(1, 7);
        $planets = new HangarPlanetRepository([$planet]);
        $fleets = new HangarFleetRepository();
        $garrisonId = $fleets->seedFleet(7, 1, null, ['fighter' => 5], true);

        $useCase = new RenameIdleFleet($planets, $fleets);
        $result = $useCase->execute(7, 1, $garrisonId, 'Interdit');

        self::assertFalse($result['success']);
        self::assertSame('La garnison ne peut pas être renommée.', $result['message']);
    }

    public function testMergePartialShipsBetweenFleets(): void
    {
        $planet = $this->createPlanet(1, 9);
        $planets = new HangarPlanetRepository([$planet]);
        $fleets = new HangarFleetRepository();
        $sourceId = $fleets->createFleet(9, 1, 'Source');
        $fleets->addShipsToFleet($sourceId, 'fighter', 10);
        $targetId = $fleets->createFleet(9, 1, 'Cible');
        $fleets->addShipsToFleet($targetId, 'fighter', 3);

        $useCase = new MergeIdleFleets($planets, $fleets);
        $result = $useCase->execute(9, 1, $sourceId, $targetId, 'partial', 'fighter', 4);

        self::assertTrue($result['success']);
        self::assertSame('4 unité(s) de fighter transférées de "Source" vers "Cible".', $result['message']);

        $fleetsAfter = $fleets->listIdleFleets(1);
        $source = $this->findSummary($fleetsAfter, $sourceId);
        $target = $this->findSummary($fleetsAfter, $targetId);
        self::assertSame(['fighter' => 6], $source['ships']);
        self::assertSame(['fighter' => 7], $target['ships']);
    }

    public function testMergeWholeFleetRemovesEmptySource(): void
    {
        $planet = $this->createPlanet(2, 12);
        $planets = new HangarPlanetRepository([$planet]);
        $fleets = new HangarFleetRepository();
        $sourceId = $fleets->createFleet(12, 2, 'Delta');
        $fleets->addShipsToFleet($sourceId, 'bomber', 5);
        $targetId = $fleets->createFleet(12, 2, 'Epsilon');

        $useCase = new MergeIdleFleets($planets, $fleets);
        $result = $useCase->execute(12, 2, $sourceId, $targetId, 'all');

        self::assertTrue($result['success']);
        self::assertSame('La flotte "Delta" a été fusionnée avec "Epsilon".', $result['message']);

        $summaries = $fleets->listIdleFleets(2);
        $source = $this->findSummary($summaries, $sourceId);
        self::assertNull($source, 'Source fleet should be removed after full merge.');
        $target = $this->findSummary($summaries, $targetId);
        self::assertSame(['bomber' => 5], $target['ships']);
    }

    public function testMergeFailsWhenQuantityTooHigh(): void
    {
        $planet = $this->createPlanet(3, 50);
        $planets = new HangarPlanetRepository([$planet]);
        $fleets = new HangarFleetRepository();
        $sourceId = $fleets->createFleet(50, 3, 'Omega');
        $fleets->addShipsToFleet($sourceId, 'destroyer', 2);
        $targetId = $fleets->createFleet(50, 3, 'Sigma');

        $useCase = new MergeIdleFleets($planets, $fleets);
        $result = $useCase->execute(50, 3, $sourceId, $targetId, 'partial', 'destroyer', 5);

        self::assertFalse($result['success']);
        self::assertSame('Quantité demandée supérieure au stock disponible.', $result['message']);
    }

    private function findSummary(array $summaries, int $fleetId): ?array
    {
        foreach ($summaries as $summary) {
            if ($summary['id'] === $fleetId) {
                return $summary;
            }
        }

        return null;
    }

    private function createPlanet(int $id, int $owner): Planet
    {
        return new Planet(
            $id,
            $owner,
            1,
            1,
            1,
            'Gaia',
            12000,
            -20,
            40,
            5000,
            5000,
            5000,
            0,
            0,
            0,
            0,
            0,
            100000,
            100000,
            100000,
            1000,
            new DateTimeImmutable()
        );
    }
}

/**
 * @implements PlanetRepositoryInterface<Planet>
 */
final class HangarPlanetRepository implements PlanetRepositoryInterface
{
    /** @var array<int, Planet> */
    private array $planets = [];

    /**
     * @param array<int, Planet> $planets
     */
    public function __construct(array $planets)
    {
        foreach ($planets as $planet) {
            $this->planets[$planet->getId()] = $planet;
        }
    }

    public function find(int $id): ?Planet
    {
        return $this->planets[$id] ?? null;
    }

    public function findByUser(int $userId): array
    {
        return array_values(array_filter($this->planets, static fn (Planet $planet): bool => $planet->getUserId() === $userId));
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
}

final class HangarFleetRepository implements FleetRepositoryInterface
{
    /** @var array<int, array{id: int, player_id: int, origin_planet_id: int, name: string|null, ships: array<string, int>, is_garrison: bool}> */
    private array $fleets = [];

    private int $nextId = 1;

    public function getFleet(int $planetId): array
    {
        return [];
    }

    public function addShips(int $planetId, string $key, int $quantity): void
    {
    }

    public function listIdleFleets(int $planetId): array
    {
        $result = [];
        foreach ($this->fleets as $fleet) {
            if ($fleet['origin_planet_id'] !== $planetId) {
                continue;
            }

            $result[] = [
                'id' => $fleet['id'],
                'name' => $fleet['name'],
                'total' => array_sum($fleet['ships']),
                'ships' => $fleet['ships'],
                'is_garrison' => $fleet['is_garrison'],
            ];
        }

        return $result;
    }

    public function createFleet(int $playerId, int $planetId, string $name): int
    {
        return $this->seedFleet($playerId, $planetId, $name, [], false);
    }

    public function addShipsToFleet(int $fleetId, string $key, int $quantity): void
    {
        if (!isset($this->fleets[$fleetId])) {
            throw new \RuntimeException('Fleet not found');
        }

        $this->fleets[$fleetId]['ships'][$key] = ($this->fleets[$fleetId]['ships'][$key] ?? 0) + $quantity;
    }

    public function findIdleFleet(int $fleetId): ?array
    {
        if (!isset($this->fleets[$fleetId])) {
            return null;
        }

        $fleet = $this->fleets[$fleetId];

        return [
            'id' => $fleet['id'],
            'player_id' => $fleet['player_id'],
            'origin_planet_id' => $fleet['origin_planet_id'],
            'name' => $fleet['name'],
        ];
    }

    public function renameFleet(int $fleetId, string $name): void
    {
        if (!isset($this->fleets[$fleetId])) {
            throw new \RuntimeException('Fleet not found');
        }

        $this->fleets[$fleetId]['name'] = $name;
    }

    public function transferShipsBetweenFleets(int $sourceFleetId, int $targetFleetId, array $shipQuantities, bool $deleteSourceIfEmpty): void
    {
        if (!isset($this->fleets[$sourceFleetId], $this->fleets[$targetFleetId])) {
            throw new \RuntimeException('Fleet not found');
        }

        foreach ($shipQuantities as $key => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $available = $this->fleets[$sourceFleetId]['ships'][$key] ?? 0;
            if ($available < $quantity) {
                throw new \RuntimeException('Quantité insuffisante pour le transfert.');
            }

            $this->fleets[$sourceFleetId]['ships'][$key] = $available - $quantity;
            if ($this->fleets[$sourceFleetId]['ships'][$key] === 0) {
                unset($this->fleets[$sourceFleetId]['ships'][$key]);
            }

            $this->fleets[$targetFleetId]['ships'][$key] = ($this->fleets[$targetFleetId]['ships'][$key] ?? 0) + $quantity;
        }

        if ($deleteSourceIfEmpty && empty($this->fleets[$sourceFleetId]['ships']) && !$this->fleets[$sourceFleetId]['is_garrison']) {
            unset($this->fleets[$sourceFleetId]);
        }
    }

    public function seedFleet(int $playerId, int $planetId, ?string $name, array $ships, bool $isGarrison): int
    {
        $id = $this->nextId++;
        $this->fleets[$id] = [
            'id' => $id,
            'player_id' => $playerId,
            'origin_planet_id' => $planetId,
            'name' => $name,
            'ships' => $ships,
            'is_garrison' => $isGarrison,
        ];

        return $id;
    }
}
