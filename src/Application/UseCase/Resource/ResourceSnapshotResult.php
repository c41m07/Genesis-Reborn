<?php

declare(strict_types=1);

namespace App\Application\UseCase\Resource;

use App\Domain\Entity\Planet;

final class ResourceSnapshotResult
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $payload,
        private readonly ?Planet $planet
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPlanet(): ?Planet
    {
        return $this->planet;
    }
}
