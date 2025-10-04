<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Fleet;

use App\Application\UseCase\Fleet\AssembleFleetFromHangar;
use App\Domain\Entity\Planet;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\HangarRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AssembleFleetFromHangarTest extends TestCase
{
    public function testExecuteTransfersShipsWhenStockIsSufficient(): void
    {
        $planet = $this->createPlanet(1, 99);
        $planets = new StubPlanetRepository([$planet]);
        $hangar = new StubHangarRepository([1 => ['fighter' => 5]]);
        $fleets = new StubFleetRepository();

        $useCase = new AssembleFleetFromHangar($planets, $hangar, $fleets);
        $result = $useCase->execute(99, 1, 'fighter', 3);

        self::assertTrue($result['success']);
        self::assertSame('Garnison renforcée avec succès.', $result['message']);
        self::assertSame(2, $hangar->getQuantity(1, 'fighter'));
        self::assertSame(['planet' => 1, 'ship' => 'fighter', 'quantity' => 3], $fleets->lastAddition);
    }

    public function testExecuteCreatesNamedFleet(): void
    {
        $planet = $this->createPlanet(2, 55);
        $planets = new StubPlanetRepository([$planet]);
        $hangar = new StubHangarRepository([2 => ['bomber' => 4]]);
        $fleets = new StubFleetRepository();

        $useCase = new AssembleFleetFromHangar($planets, $hangar, $fleets);
        $result = $useCase->execute(55, 2, 'bomber', 2, null, 'Escadre Alpha');

        self::assertTrue($result['success']);
        self::assertSame('Flotte "Escadre Alpha" renforcée avec succès.', $result['message']);
        self::assertSame(2, $hangar->getQuantity(2, 'bomber'));
        self::assertSame(['fleet' => 1, 'ship' => 'bomber', 'quantity' => 2], $fleets->lastFleetAddition);
        self::assertSame('Escadre Alpha', $fleets->idleFleets[1]['name']);
    }

    public function testExecuteAddsToExistingFleet(): void
    {
        $planet = $this->createPlanet(3, 77);
        $planets = new StubPlanetRepository([$planet]);
        $hangar = new StubHangarRepository([3 => ['scout' => 6]]);
        $fleets = new StubFleetRepository();
        $fleets->idleFleets[10] = [
            'id' => 10,
            'player_id' => 77,
            'origin_planet_id' => 3,
            'name' => 'Recon 1',
        ];

        $useCase = new AssembleFleetFromHangar($planets, $hangar, $fleets);
        $result = $useCase->execute(77, 3, 'scout', 4, 10);

        self::assertTrue($result['success']);
        self::assertSame('Flotte "Recon 1" renforcée avec succès.', $result['message']);
        self::assertSame(2, $hangar->getQuantity(3, 'scout'));
        self::assertSame(['fleet' => 10, 'ship' => 'scout', 'quantity' => 4], $fleets->lastFleetAddition);
    }

    public function testExecuteRejectsInvalidFleetName(): void
    {
        $planet = $this->createPlanet(4, 12);
        $useCase = new AssembleFleetFromHangar(new StubPlanetRepository([$planet]), new StubHangarRepository([4 => ['frigate' => 2]]), new StubFleetRepository());

        $result = $useCase->execute(12, 4, 'frigate', 1, null, 'X');

        self::assertFalse($result['success']);
        self::assertSame('Le nom de la flotte doit contenir au moins 3 caractères.', $result['message']);
    }

    public function testExecuteRejectsFleetFromAnotherPlayer(): void
    {
        $planet = $this->createPlanet(5, 44);
        $hangar = new StubHangarRepository([5 => ['destroyer' => 3]]);
        $fleets = new StubFleetRepository();
        $fleets->idleFleets[99] = [
            'id' => 99,
            'player_id' => 123,
            'origin_planet_id' => 5,
            'name' => 'Intrus',
        ];

        $useCase = new AssembleFleetFromHangar(new StubPlanetRepository([$planet]), $hangar, $fleets);
        $result = $useCase->execute(44, 5, 'destroyer', 2, 99);

        self::assertFalse($result['success']);
        self::assertSame('Flotte introuvable ou inaccessible.', $result['message']);
        self::assertSame(3, $hangar->getQuantity(5, 'destroyer'));
    }

    public function testExecuteFailsWhenQuantityIsNonPositive(): void
    {
        $planet = $this->createPlanet(1, 99);
        $useCase = new AssembleFleetFromHangar(new StubPlanetRepository([$planet]), new StubHangarRepository([]), new StubFleetRepository());

        $result = $useCase->execute(99, 1, 'fighter', 0);

        self::assertFalse($result['success']);
        self::assertSame('Veuillez sélectionner une quantité positive.', $result['message']);
    }

    public function testExecuteFailsWhenPlanetDoesNotBelongToPlayer(): void
    {
        $planet = $this->createPlanet(1, 42);
        $useCase = new AssembleFleetFromHangar(new StubPlanetRepository([$planet]), new StubHangarRepository([]), new StubFleetRepository());

        $result = $useCase->execute(99, 1, 'fighter', 2);

        self::assertFalse($result['success']);
        self::assertSame('Planète inaccessible.', $result['message']);
    }

    public function testExecuteFailsWhenStockIsInsufficient(): void
    {
        $planet = $this->createPlanet(1, 99);
        $hangar = new StubHangarRepository([1 => ['fighter' => 1]]);
        $useCase = new AssembleFleetFromHangar(new StubPlanetRepository([$planet]), $hangar, new StubFleetRepository());

        $result = $useCase->execute(99, 1, 'fighter', 3);

        self::assertFalse($result['success']);
        self::assertSame('Stock insuffisant dans le hangar.', $result['message']);
        self::assertSame(1, $hangar->getQuantity(1, 'fighter'));
    }

    private function createPlanet(int $id, int $ownerId): Planet
    {
        return new Planet($id, $ownerId, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000, new DateTimeImmutable());
    }
}

/**
 * @implements PlanetRepositoryInterface<Planet>
 */
final class StubPlanetRepository implements PlanetRepositoryInterface
{
    /** @var array<int, Planet> */
    private array $planets;

    /**
     * @param array<int, Planet> $planets
     */
    public function __construct(array $planets)
    {
        $this->planets = [];
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

final class StubHangarRepository implements HangarRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $stock;

    /**
     * @param array<int, array<string, int>> $stock
     */
    public function __construct(array $stock)
    {
        $this->stock = $stock;
    }

    public function getStock(int $planetId): array
    {
        return $this->stock[$planetId] ?? [];
    }

    public function getQuantity(int $planetId, string $shipKey): int
    {
        return $this->stock[$planetId][$shipKey] ?? 0;
    }

    public function addShips(int $planetId, string $shipKey, int $quantity): void
    {
        $this->stock[$planetId][$shipKey] = ($this->stock[$planetId][$shipKey] ?? 0) + $quantity;
    }

    public function removeShips(int $planetId, string $shipKey, int $quantity): void
    {
        $current = $this->stock[$planetId][$shipKey] ?? 0;
        if ($current < $quantity) {
            throw new \RuntimeException('Quantité insuffisante dans le hangar.');
        }

        $remaining = $current - $quantity;
        if ($remaining > 0) {
            $this->stock[$planetId][$shipKey] = $remaining;
        } else {
            unset($this->stock[$planetId][$shipKey]);
        }
    }
}

final class StubFleetRepository implements FleetRepositoryInterface
{
    public ?array $lastAddition = null;
    public ?array $lastFleetAddition = null;
    /** @var array<int, array{id: int, player_id: int, origin_planet_id: int, name: string|null, ships: array<string, int>}> */
    public array $idleFleets = [];
    private int $nextFleetId = 1;

    public function getFleet(int $planetId): array
    {
        return [];
    }

    public function addShips(int $planetId, string $key, int $quantity): void
    {
        $this->lastAddition = ['planet' => $planetId, 'ship' => $key, 'quantity' => $quantity];
    }

    public function listIdleFleets(int $planetId): array
    {
        $result = [];
        foreach ($this->idleFleets as $fleet) {
            if ($fleet['origin_planet_id'] !== $planetId) {
                continue;
            }

            $total = array_sum($fleet['ships']);
            $result[] = [
                'id' => $fleet['id'],
                'name' => $fleet['name'],
                'total' => $total,
                'ships' => $fleet['ships'],
                'is_garrison' => false,
            ];
        }

        return $result;
    }

    public function createFleet(int $playerId, int $planetId, string $name): int
    {
        $id = $this->nextFleetId++;
        $this->idleFleets[$id] = [
            'id' => $id,
            'player_id' => $playerId,
            'origin_planet_id' => $planetId,
            'name' => $name,
            'ships' => [],
        ];

        return $id;
    }

    public function addShipsToFleet(int $fleetId, string $key, int $quantity): void
    {
        $this->lastFleetAddition = ['fleet' => $fleetId, 'ship' => $key, 'quantity' => $quantity];
        if (!isset($this->idleFleets[$fleetId])) {
            return;
        }

        $this->idleFleets[$fleetId]['ships'][$key] = ($this->idleFleets[$fleetId]['ships'][$key] ?? 0) + $quantity;
    }

    public function findIdleFleet(int $fleetId): ?array
    {
        return $this->idleFleets[$fleetId] ?? null;
    }

    public function renameFleet(int $fleetId, string $name): void
    {
        if (!isset($this->idleFleets[$fleetId])) {
            throw new \RuntimeException('Flotte introuvable.');
        }

        $this->idleFleets[$fleetId]['name'] = $name;
    }

    public function transferShipsBetweenFleets(int $sourceFleetId, int $targetFleetId, array $shipQuantities, bool $deleteSourceIfEmpty): void
    {
        if (!isset($this->idleFleets[$sourceFleetId], $this->idleFleets[$targetFleetId])) {
            throw new \RuntimeException('Flotte introuvable.');
        }

        foreach ($shipQuantities as $key => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $available = $this->idleFleets[$sourceFleetId]['ships'][$key] ?? 0;
            if ($available < $quantity) {
                throw new \RuntimeException('Quantité insuffisante pour le transfert.');
            }

            $this->idleFleets[$sourceFleetId]['ships'][$key] = $available - $quantity;
            if ($this->idleFleets[$sourceFleetId]['ships'][$key] === 0) {
                unset($this->idleFleets[$sourceFleetId]['ships'][$key]);
            }

            $this->idleFleets[$targetFleetId]['ships'][$key] = ($this->idleFleets[$targetFleetId]['ships'][$key] ?? 0) + $quantity;
        }

        if ($deleteSourceIfEmpty && empty($this->idleFleets[$sourceFleetId]['ships'])) {
            unset($this->idleFleets[$sourceFleetId]);
        }
    }
}
