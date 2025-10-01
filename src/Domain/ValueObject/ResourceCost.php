<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final class ResourceCost
{
    /**
     * @param array<string, int> $amounts
     */
    private function __construct(private readonly array $amounts)
    {
    }

    /**
     * @param array<string, int|float> $amounts
     */
    public static function fromArray(array $amounts): self
    {
        $normalized = [];

        foreach ($amounts as $resource => $value) {
            if (!is_string($resource) || $resource === '') {
                throw new InvalidArgumentException('Resource name must be a non-empty string.');
            }

            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException(sprintf('Resource "%s" must be numeric.', $resource));
            }

            $rounded = (int)round((float)$value);
            if ($rounded < 0) {
                throw new InvalidArgumentException(sprintf('Resource "%s" cannot have a negative cost.', $resource));
            }

            $normalized[$resource] = $rounded;
        }

        return new self($normalized);
    }

    public static function fromStock(ResourceStock $stock): self
    {
        return new self($stock->toArray());
    }

    public function amount(string $resource): int
    {
        return $this->amounts[$resource] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->amounts;
    }

    /**
     * @param callable(int): (float|int) $transformer
     */
    public function map(callable $transformer): self
    {
        $mapped = [];

        foreach ($this->amounts as $resource => $amount) {
            $value = $transformer($amount);
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Mapped resource cost must remain numeric.');
            }

            $mapped[$resource] = (int)round((float)$value);
        }

        return new self($mapped);
    }
}
