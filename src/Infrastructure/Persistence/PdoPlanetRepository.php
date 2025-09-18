<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Planet;
use App\Domain\Repository\PlanetRepositoryInterface;
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
        $stmt = $this->pdo->prepare('SELECT * FROM planets WHERE user_id = :user ORDER BY id ASC');
        $stmt->execute(['user' => $userId]);
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

    public function createHomeworld(int $userId): Planet
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM planets WHERE user_id = :user FOR UPDATE');
            $stmt->execute(['user' => $userId]);
            $row = $stmt->fetch();

            if ($row) {
                $this->pdo->commit();
                return $this->hydrate($row);
            }

            $this->pdo->prepare('INSERT INTO planets (user_id, name, metal, crystal, hydrogen, energy, prod_metal_per_hour, prod_crystal_per_hour, prod_hydrogen_per_hour, prod_energy_per_hour)
                VALUES (:user, :name, :metal, :crystal, :hydrogen, :energy, :mPH, :cPH, :hPH, :ePH)')->execute([
                'user' => $userId,
                'name' => 'Planète mère',
                'metal' => 500,
                'crystal' => 250,
                'hydrogen' => 0,
                'energy' => 0,
                'mPH' => 100,
                'cPH' => 50,
                'hPH' => 0,
                'ePH' => 0,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $planet = $this->find($id);
            $this->pdo->commit();

            if (!$planet) {
                throw new RuntimeException('Impossible de charger la planète nouvellement créée.');
            }

            return $planet;
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function update(Planet $planet): void
    {
        $stmt = $this->pdo->prepare('UPDATE planets SET name = :name, metal = :metal, crystal = :crystal, hydrogen = :hydrogen, energy = :energy,
            prod_metal_per_hour = :mPH, prod_crystal_per_hour = :cPH, prod_hydrogen_per_hour = :hPH, prod_energy_per_hour = :ePH WHERE id = :id');

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
            (int) $data['user_id'],
            $data['name'],
            (int) $data['metal'],
            (int) $data['crystal'],
            (int) $data['hydrogen'],
            (int) $data['energy'],
            (int) $data['prod_metal_per_hour'],
            (int) $data['prod_crystal_per_hour'],
            (int) $data['prod_hydrogen_per_hour'],
            (int) $data['prod_energy_per_hour']
        );
    }
}
