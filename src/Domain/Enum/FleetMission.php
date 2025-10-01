<?php

declare(strict_types=1);

namespace App\Domain\Enum;

use InvalidArgumentException;
use ValueError;

enum FleetMission: string
{
    case Idle = 'idle';
    case Transport = 'transport';
    case Attack = 'attack';
    case Harvest = 'harvest';
    case Expedition = 'expedition';
    case Pve = 'pve';
    case Explore = 'explore';

    public static function fromString(string $value): self
    {
        try {
            return self::from(strtolower($value));
        } catch (ValueError $exception) {
            throw new InvalidArgumentException(sprintf('Unknown fleet mission "%s".', $value), 0, $exception);
        }
    }

    public function isIdle(): bool
    {
        return $this === self::Idle;
    }
}
