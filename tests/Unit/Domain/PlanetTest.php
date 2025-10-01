<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Entity\Planet;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlanetTest extends TestCase
{
    public function testResourceMutatorsUpdateValues(): void
    {
        $planet = $this->createPlanet();

        $planet->setMetal(2000);
        $planet->setCrystal(1000);
        $planet->setHydrogen(500);
        $planet->setEnergy(250);
        $planet->setMetalPerHour(600);
        $planet->setCrystalPerHour(300);
        $planet->setHydrogenPerHour(150);
        $planet->setEnergyPerHour(75);
        $planet->setMetalCapacity(18000);
        $planet->setCrystalCapacity(16000);
        $planet->setHydrogenCapacity(14000);
        $planet->setEnergyCapacity(12000);
        $now = new DateTimeImmutable();
        $planet->setLastResourceTick($now);

        self::assertSame(2000, $planet->getMetal());
        self::assertSame(1000, $planet->getCrystal());
        self::assertSame(500, $planet->getHydrogen());
        self::assertSame(250, $planet->getEnergy());
        self::assertSame(600, $planet->getMetalPerHour());
        self::assertSame(300, $planet->getCrystalPerHour());
        self::assertSame(150, $planet->getHydrogenPerHour());
        self::assertSame(75, $planet->getEnergyPerHour());
        self::assertSame(18000, $planet->getMetalCapacity());
        self::assertSame(16000, $planet->getCrystalCapacity());
        self::assertSame(14000, $planet->getHydrogenCapacity());
        self::assertSame(12000, $planet->getEnergyCapacity());
        self::assertSame($now, $planet->getLastResourceTick());
    }

    private function createPlanet(): Planet
    {
        return new Planet(
            1,
            1,
            1,
            1,
            1,
            'Origin',
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
