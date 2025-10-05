<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\Fleet;

use App\Application\UseCase\Fleet\ProcessFleetReturns;
use App\Domain\Entity\FleetMovement;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProcessFleetReturnsTest extends TestCase
{
    public function testExecuteCompletesAllDueReturns(): void
    {
        $movementRepository = $this->createMock(FleetMovementRepositoryInterface::class);
        $movement1 = $this->createStub(FleetMovement::class);
        $movement2 = $this->createStub(FleetMovement::class);

        $now = new DateTimeImmutable('now');

        $movementRepository->expects(self::once())
            ->method('findReturningMissions')
            ->with($now, 42)
            ->willReturn([$movement1, $movement2]);

        $movementRepository->expects(self::exactly(2))
            ->method('completeReturn')
            ->withConsecutive([
                $movement1,
                $now,
            ], [
                $movement2,
                $now,
            ]);

        $useCase = new ProcessFleetReturns($movementRepository);
        $count = $useCase->execute(42, $now);

        self::assertSame(2, $count);
    }
}
