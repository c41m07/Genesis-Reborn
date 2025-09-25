<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface ResearchStateRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function getLevels(int $planetId): array;

    public function setLevel(int $planetId, string $key, int $level): void;
}
