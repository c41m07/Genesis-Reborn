<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

final class ResourceStock
{
    /**
     * @param array<string, int> $amounts
     */
    private function __construct(private readonly array $amounts)
    {
    }

    /**
     * @param array<string, int> $amounts
     */
    public static function fromArray(array $amounts): self
    {
        $normalized = [];

        foreach ($amounts as $resource => $value) {
            if (!is_string($resource) || $resource === '') {
                throw new InvalidArgumentException('Resource name must be a non-empty string.');
            }

            if (!is_int($value)) {
                throw new InvalidArgumentException(sprintf('Resource "%s" must be provided as an integer.', $resource));
            }

            if ($value < 0) {
                throw new InvalidArgumentException(sprintf('Resource "%s" cannot have a negative quantity.', $resource));
            }

            $normalized[$resource] = $value;
        }

        return new self($normalized);
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
}
