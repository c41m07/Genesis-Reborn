<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Planet;
use App\Domain\Repository\PlanetRepositoryInterface;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

class PdoPlanetRepository implements PlanetRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return Planet[] */
    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM planets WHERE player_id = :player ORDER BY id ASC');
        $stmt->execute(['player' => $userId]);
        $planets = [];

        while ($row = $stmt->fetch()) {
            $planets[] = $this->hydrate($row);
        }

        return $planets;
    }

    public function find(int $id): ?Planet
    {
        $stmt = $this->pdo->prepare('SELECT * FROM planets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /** @return Planet[] */
    public function findByCoordinates(int $galaxy, int $system): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM planets WHERE galaxy = :galaxy AND `system` = :system ORDER BY position ASC');
        $stmt->execute([
            'galaxy' => $galaxy,
            'system' => $system,
        ]);

        $planets = [];
        while ($row = $stmt->fetch()) {
            $planets[] = $this->hydrate($row);
        }

        return $planets;
    }

    public function createHomeworld(int $userId): Planet
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM planets WHERE player_id = :player AND is_homeworld = 1 FOR UPDATE');
            $stmt->execute(['player' => $userId]);
            $row = $stmt->fetch();

            if ($row) {
                $this->pdo->commit();
                return $this->hydrate($row);
            }

            $diameter = 12000;
            $temperatureMin = -20;
            $temperatureMax = 40;

            $initialMetal = 1000;
            $initialCrystal = 1000;
            $initialHydrogen = 1000;
            $initialEnergy = 0;

            $metalCapacity = 1000;
            $crystalCapacity = 1000;
            $hydrogenCapacity = 1000;
            $energyCapacity = 1000;

            $now = new DateTimeImmutable('now');
            $statement = $this->pdo->prepare(
                'INSERT INTO planets (player_id, name, galaxy, `system`, `position`, diameter, temperature_min, temperature_max, is_homeworld, metal, crystal, hydrogen, prod_metal_per_hour, prod_crystal_per_hour, prod_hydrogen_per_hour, prod_energy_per_hour, energy, metal_capacity, crystal_capacity, hydrogen_capacity, energy_capacity, last_resource_tick, created_at, updated_at)
                VALUES (:player, :name, :galaxy, :system, :position, :diameter, :tmin, :tmax, 1, :metal, :crystal, :hydrogen, :mPH, :cPH, :hPH, :ePH, :energy, :metalCap, :crystalCap, :hydrogenCap, :energyCap, :now, :now, :now)'
            );

            $maxAttempts = 1000;
            $planetId = null;

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $galaxy = random_int(1, 9);
                $system = random_int(1, 9);
                $position = random_int(1, 9);

                try {
                    $statement->execute([
                        'player' => $userId,
                        'name' => 'Planète mère',
                        'galaxy' => $galaxy,
                        'system' => $system,
                        'position' => $position,
                        'diameter' => $diameter,
                        'tmin' => $temperatureMin,
                        'tmax' => $temperatureMax,
                        'metal' => $initialMetal,
                        'crystal' => $initialCrystal,
                        'hydrogen' => $initialHydrogen,
                        'mPH' => 0,
                        'cPH' => 0,
                        'hPH' => 0,
                        'ePH' => 0,
                        'energy' => $initialEnergy,
                        'metalCap' => $metalCapacity,
                        'crystalCap' => $crystalCapacity,
                        'hydrogenCap' => $hydrogenCapacity,
                        'energyCap' => $energyCapacity,
                        'now' => $now->format('Y-m-d H:i:s'),
                    ]);

                    $planetId = (int) $this->pdo->lastInsertId();
                    break;
                } catch (PDOException $exception) {
                    if (isset($exception->errorInfo[1]) && $exception->errorInfo[1] === 1062) {
                        continue;
                    }

                    throw $exception;
                }
            }

            if ($planetId === null) {
                throw new RuntimeException('Impossible de trouver des coordonnées libres pour la planète de départ.');
            }

            $planet = $this->find($planetId);

            if (!$planet) {
                throw new RuntimeException('Impossible de charger la planète nouvellement créée.');
            }

            $this->pdo->commit();

            return $planet;
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        } catch (RuntimeException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function update(Planet $planet): void
    {
        $stmt = $this->pdo->prepare('UPDATE planets SET name = :name, metal = :metal, crystal = :crystal, hydrogen = :hydrogen, energy = :energy,
            prod_metal_per_hour = :mPH, prod_crystal_per_hour = :cPH, prod_hydrogen_per_hour = :hPH, prod_energy_per_hour = :ePH,
            metal_capacity = :metalCapacity, crystal_capacity = :crystalCapacity, hydrogen_capacity = :hydrogenCapacity, energy_capacity = :energyCapacity,
            last_resource_tick = :lastTick WHERE id = :id');

        $stmt->execute([
            'name' => $planet->getName(),
            'metal' => $planet->getMetal(),
            'crystal' => $planet->getCrystal(),
            'hydrogen' => $planet->getHydrogen(),
            'energy' => $planet->getEnergy(),
            'mPH' => $planet->getMetalPerHour(),
            'cPH' => $planet->getCrystalPerHour(),
            'hPH' => $planet->getHydrogenPerHour(),
            'ePH' => $planet->getEnergyPerHour(),
            'metalCapacity' => $planet->getMetalCapacity(),
            'crystalCapacity' => $planet->getCrystalCapacity(),
            'hydrogenCapacity' => $planet->getHydrogenCapacity(),
            'energyCapacity' => $planet->getEnergyCapacity(),
            'lastTick' => $planet->getLastResourceTick()->format('Y-m-d H:i:s'),
            'id' => $planet->getId(),
        ]);
    }

    public function rename(int $planetId, string $name): void
    {
        $stmt = $this->pdo->prepare('UPDATE planets SET name = :name WHERE id = :id');
        $stmt->execute([
            'name' => $name,
            'id' => $planetId,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): Planet
    {
        return new Planet(
            (int) $data['id'],
            (int) $data['player_id'],
            (int) ($data['galaxy'] ?? 0),
            (int) ($data['system'] ?? 0),
            (int) ($data['position'] ?? 0),
            $data['name'],
            (int) ($data['diameter'] ?? 0),
            (int) ($data['temperature_min'] ?? 0),
            (int) ($data['temperature_max'] ?? 0),
            (int) $data['metal'],
            (int) $data['crystal'],
            (int) $data['hydrogen'],
            (int) $data['energy'],
            (int) ($data['prod_metal_per_hour'] ?? 0),
            (int) ($data['prod_crystal_per_hour'] ?? 0),
            (int) ($data['prod_hydrogen_per_hour'] ?? 0),
            (int) ($data['prod_energy_per_hour'] ?? 0),
            (int) ($data['metal_capacity'] ?? 0),
            (int) ($data['crystal_capacity'] ?? 0),
            (int) ($data['hydrogen_capacity'] ?? 0),
            (int) ($data['energy_capacity'] ?? 0),
            isset($data['last_resource_tick']) ? new DateTimeImmutable($data['last_resource_tick']) : null
        );
    }
}
