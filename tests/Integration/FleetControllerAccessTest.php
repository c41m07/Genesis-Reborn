<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Service\ProcessShipBuildQueue;
use App\Application\Service\Queue\QueueFinalizer;
use App\Application\UseCase\Fleet\PlanFleetMission;
use App\Application\UseCase\Fleet\ProcessFleetArrivals;
use App\Controller\FleetController;
use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\HangarRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\Session;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class FleetControllerAccessTest extends TestCase
{
    public function testIndexRedirectsWhenShipyardUnavailable(): void
    {
        [$controller, $session] = $this->createController(false);

        $request = new Request('GET', '/fleet', ['planet' => 1], [], $session, []);
        $response = $controller->index($request);

        self::assertSame(302, $this->getResponseStatus($response));
        self::assertSame('https://example.com/colony?planet=1', $this->getResponseHeader($response, 'Location'));

        $flashes = $session->toArray()['_flashes']['messages'] ?? [];
        self::assertNotEmpty($flashes);
        self::assertSame('warning', $flashes[0]['type'] ?? null);
        self::assertSame('Pour gérer votre flotte, vous devez construire un chantier spatial.', $flashes[0]['message'] ?? null);
    }

    /**
     * @return array{0: FleetController, 1: Session}
     */
    private function createController(bool $hasShipyard): array
    {
        $storage = ['user_id' => 42];
        $session = new Session($storage);
        $flashBag = new FlashBag($session);
        $csrfTokenManager = new CsrfTokenManager($session);

        $planet = new Planet(1, 42, 1, 1, 1, 'Gaia', 12000, -20, 40, 5000, 5000, 5000, 0, 0, 0, 0, 0, 100000, 100000, 100000, 1000);
        $planetRepository = new TestPlanetRepository([$planet]);
        $buildingStates = new TestBuildingStateRepository([
            1 => [
                'research_lab' => 0,
                'shipyard' => $hasShipyard ? 1 : 0,
            ],
        ]);
        $fleetRepository = new TestFleetRepository();
        $hangarRepository = new TestHangarRepository();
        $shipQueueRepository = new TestShipBuildQueueRepository();
        $shipQueueProcessor = new ProcessShipBuildQueue($shipQueueRepository, $hangarRepository, new QueueFinalizer());

        $shipCatalog = new ShipCatalog([]);
        $movements = $this->createMock(FleetMovementRepositoryInterface::class);
        $planFleetMission = $this->createMock(PlanFleetMission::class);
        $processArrivals = $this->createMock(ProcessFleetArrivals::class);
        $processArrivals->expects($hasShipyard ? self::once() : self::never())
            ->method('execute')
            ->with(42, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(0);
        $renderer = new class () extends ViewRenderer {
            public function __construct()
            {
                parent::__construct(__DIR__);
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function render(string $template, array $parameters = []): string
            {
                return 'rendered';
            }
        };

        $controller = new FleetController(
            $planetRepository,
            $buildingStates,
            $fleetRepository,
            $movements,
            $shipCatalog,
            $shipQueueProcessor,
            $planFleetMission,
            $processArrivals,
            $renderer,
            $session,
            $flashBag,
            $csrfTokenManager,
            'https://example.com'
        );

        return [$controller, $session];
    }

    private function getResponseStatus(Response $response): int
    {
        return $response->getStatusCode();
    }

    private function getResponseHeader(Response $response, string $name): ?string
    {
        $headers = method_exists($response, 'getHeaders') ? $response->getHeaders() : [];

        return $headers[$name] ?? null;
    }

    public function testIndexReturnsJsonErrorWhenShipyardUnavailable(): void
    {
        [$controller, $session] = $this->createController(false);

        $request = new Request('GET', '/fleet', ['planet' => 1], [], $session, ['Accept' => 'application/json']);
        $response = $controller->index($request);

        self::assertSame(403, $this->getResponseStatus($response));
        self::assertSame('application/json', $this->getResponseHeader($response, 'Content-Type'));

        $payload = json_decode($this->getResponseContent($response), true);
        self::assertIsArray($payload);
        self::assertSame([
            'success' => false,
            'message' => 'Pour gérer votre flotte, vous devez construire un chantier spatial.',
            'planetId' => 1,
        ], $payload);
    }

    private function getResponseContent(Response $response): string
    {
        return (string)$response->getContent();
    }

    public function testIndexRendersWhenShipyardAvailable(): void
    {
        [$controller, $session] = $this->createController(true);

        $request = new Request('GET', '/fleet', ['planet' => 1], [], $session, []);
        $response = $controller->index($request);

        self::assertSame(200, $this->getResponseStatus($response));
        self::assertSame('rendered', $this->getResponseContent($response));
    }
}

/**
 * @implements PlanetRepositoryInterface<Planet>
 */
final class TestPlanetRepository implements PlanetRepositoryInterface
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

    public function findByUser(int $userId): array
    {
        return array_values(array_filter($this->planets, static fn (Planet $planet): bool => $planet->getUserId() === $userId));
    }

    public function find(int $id): ?Planet
    {
        return $this->planets[$id] ?? null;
    }

    public function findByCoordinates(int $galaxy, int $system): array
    {
        return array_values(array_filter(
            $this->planets,
            static fn (Planet $planet): bool => $planet->getGalaxy() === $galaxy && $planet->getSystem() === $system
        ));
    }

    public function createHomeworld(int $userId): Planet
    {
        throw new \RuntimeException('Not implemented.');
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

final class TestBuildingStateRepository implements BuildingStateRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $levels;

    /**
     * @param array<int, array<string, int>> $levels
     */
    public function __construct(array $levels)
    {
        $this->levels = $levels;
    }

    public function getLevels(int $planetId): array
    {
        return $this->levels[$planetId] ?? [];
    }

    public function setLevel(int $planetId, string $buildingKey, int $level): void
    {
        $this->levels[$planetId][$buildingKey] = $level;
    }
}

final class TestFleetRepository implements FleetRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $fleets = [];
    /** @var array<int, array{id: int, player_id: int, origin_planet_id: int, name: string|null, ships: array<string, int>, is_garrison: bool}> */
    private array $fleetMeta = [];
    /** @var array<int, int> */
    private array $garrisons = [];
    private int $nextFleetId = 1;

    public function getFleet(int $planetId): array
    {
        return $this->fleets[$planetId] ?? [];
    }

    public function addShips(int $planetId, string $key, int $quantity): void
    {
        $this->fleets[$planetId][$key] = ($this->fleets[$planetId][$key] ?? 0) + $quantity;
        $fleetId = $this->ensureGarrison($planetId);
        $this->fleetMeta[$fleetId]['ships'][$key] = ($this->fleetMeta[$fleetId]['ships'][$key] ?? 0) + $quantity;
        $this->fleetMeta[$fleetId]['is_garrison'] = true;
    }

    public function listIdleFleets(int $planetId): array
    {
        $this->ensureGarrison($planetId);

        $result = [];
        foreach ($this->fleetMeta as $fleet) {
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
        $id = $this->nextFleetId++;
        $this->fleetMeta[$id] = [
            'id' => $id,
            'player_id' => $playerId,
            'origin_planet_id' => $planetId,
            'name' => $name,
            'ships' => [],
            'is_garrison' => false,
        ];

        return $id;
    }

    public function addShipsToFleet(int $fleetId, string $key, int $quantity): void
    {
        if (!isset($this->fleetMeta[$fleetId])) {
            throw new \RuntimeException('Fleet not found');
        }

        $this->fleetMeta[$fleetId]['ships'][$key] = ($this->fleetMeta[$fleetId]['ships'][$key] ?? 0) + $quantity;
    }

    public function findIdleFleet(int $fleetId): ?array
    {
        if (!isset($this->fleetMeta[$fleetId])) {
            return null;
        }

        $fleet = $this->fleetMeta[$fleetId];

        return [
            'id' => $fleet['id'],
            'player_id' => $fleet['player_id'],
            'origin_planet_id' => $fleet['origin_planet_id'],
            'name' => $fleet['name'],
        ];
    }

    public function renameFleet(int $fleetId, string $name): void
    {
        if (!isset($this->fleetMeta[$fleetId])) {
            throw new \RuntimeException('Fleet not found');
        }

        $this->fleetMeta[$fleetId]['name'] = $name;
    }

    public function transferShipsBetweenFleets(int $sourceFleetId, int $targetFleetId, array $shipQuantities, bool $deleteSourceIfEmpty): void
    {
        if (!isset($this->fleetMeta[$sourceFleetId], $this->fleetMeta[$targetFleetId])) {
            throw new \RuntimeException('Fleet not found');
        }

        foreach ($shipQuantities as $key => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $available = $this->fleetMeta[$sourceFleetId]['ships'][$key] ?? 0;
            if ($available < $quantity) {
                throw new \RuntimeException('Quantité insuffisante pour le transfert.');
            }

            $this->fleetMeta[$sourceFleetId]['ships'][$key] = $available - $quantity;
            if ($this->fleetMeta[$sourceFleetId]['ships'][$key] === 0) {
                unset($this->fleetMeta[$sourceFleetId]['ships'][$key]);
            }

            $this->fleetMeta[$targetFleetId]['ships'][$key] = ($this->fleetMeta[$targetFleetId]['ships'][$key] ?? 0) + $quantity;
        }

        if ($deleteSourceIfEmpty && empty($this->fleetMeta[$sourceFleetId]['ships']) && !$this->fleetMeta[$sourceFleetId]['is_garrison']) {
            unset($this->fleetMeta[$sourceFleetId]);
        }
    }

    private function ensureGarrison(int $planetId): int
    {
        if (isset($this->garrisons[$planetId])) {
            return $this->garrisons[$planetId];
        }

        $id = $this->nextFleetId++;
        $this->garrisons[$planetId] = $id;
        $this->fleetMeta[$id] = [
            'id' => $id,
            'player_id' => 0,
            'origin_planet_id' => $planetId,
            'name' => null,
            'ships' => $this->fleets[$planetId] ?? [],
            'is_garrison' => true,
        ];

        return $id;
    }
}

final class TestHangarRepository implements HangarRepositoryInterface
{
    /** @var array<int, array<string, int>> */
    private array $stock = [];

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
        if ($quantity <= 0) {
            return;
        }

        $this->stock[$planetId][$shipKey] = ($this->stock[$planetId][$shipKey] ?? 0) + $quantity;
    }

    public function removeShips(int $planetId, string $shipKey, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $current = $this->stock[$planetId][$shipKey] ?? 0;
        if ($current < $quantity) {
            throw new \RuntimeException('Quantité insuffisante.');
        }

        $remaining = $current - $quantity;
        if ($remaining > 0) {
            $this->stock[$planetId][$shipKey] = $remaining;
        } else {
            unset($this->stock[$planetId][$shipKey]);
        }
    }
}

final class TestShipBuildQueueRepository implements ShipBuildQueueRepositoryInterface
{
    public function getActiveQueue(int $planetId): array
    {
        return [];
    }

    public function countActive(int $planetId): int
    {
        return 0;
    }

    public function enqueue(int $planetId, string $shipKey, int $quantity, int $durationSeconds): void
    {
        // No-op for tests.
    }

    public function finalizeDueJobs(int $planetId): array
    {
        return [];
    }
}
