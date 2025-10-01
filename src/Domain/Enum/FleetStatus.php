<?php

declare(strict_types=1);

namespace App\Domain\Enum;

use InvalidArgumentException;
use ValueError;

enum FleetStatus: string
{
    case Idle = 'idle';
    case Outbound = 'outbound';
    case Returning = 'returning';
    case Holding = 'holding';
    case Completed = 'completed';
    case Failed = 'failed';

    public static function fromString(string $value): self
    {
        try {
            return self::from(strtolower($value));
        } catch (ValueError $exception) {
            throw new InvalidArgumentException(sprintf('Unknown fleet status "%s".', $value), 0, $exception);
        }
    }

    public function isActive(): bool
    {
        return $this === self::Outbound || $this === self::Returning || $this === self::Holding;
    }
}
