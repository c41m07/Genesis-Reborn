<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final class Coordinates
{
    private function __construct(
        private readonly int $galaxy,
        private readonly int $system,
        private readonly int $position
    ) {
    }

    public static function fromInts(int $galaxy, int $system, int $position): self
    {
        self::assertCoordinate($galaxy, 'galaxy');
        self::assertCoordinate($system, 'system');
        self::assertCoordinate($position, 'position');

        return new self($galaxy, $system, $position);
    }

    /**
     * @param array{galaxy: int, system: int, position: int} $coordinates
     */
    public static function fromArray(array $coordinates): self
    {
        if (!isset($coordinates['galaxy'], $coordinates['system'], $coordinates['position'])) {
            throw new InvalidArgumentException('Coordinates array must contain galaxy, system and position keys.');
        }

        return self::fromInts((int)$coordinates['galaxy'], (int)$coordinates['system'], (int)$coordinates['position']);
    }

    public function galaxy(): int
    {
        return $this->galaxy;
    }

    public function system(): int
    {
        return $this->system;
    }

    public function position(): int
    {
        return $this->position;
    }

    /**
     * @return array{galaxy: int, system: int, position: int}
     */
    public function toArray(): array
    {
        return [
            'galaxy' => $this->galaxy,
            'system' => $this->system,
            'position' => $this->position,
        ];
    }

    private static function assertCoordinate(int $value, string $label): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException(sprintf('Coordinate "%s" must be greater than or equal to zero.', $label));
        }
    }
}
