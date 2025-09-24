<?php

declare(strict_types=1);

namespace App\Domain\Battle\DTO;

final class FleetBattleRoundDTO
{
    /**
     * @var array<string, int>
     */
    private array $attackerLosses;

    /**
     * @var array<string, int>
     */
    private array $defenderLosses;

    /**
     * @var array<string, int>
     */
    private array $attackerRemaining;

    /**
     * @var array<string, int>
     */
    private array $defenderRemaining;

    /**
     * @param array<string, int|float> $attackerLosses
     * @param array<string, int|float> $defenderLosses
     * @param array<string, int|float> $attackerRemaining
     * @param array<string, int|float> $defenderRemaining
     */
    public function __construct(
        private readonly int $round,
        array $attackerLosses,
        array $defenderLosses,
        array $attackerRemaining,
        array $defenderRemaining
    ) {
        $this->attackerLosses = $this->sanitize($attackerLosses);
        $this->defenderLosses = $this->sanitize($defenderLosses);
        $this->attackerRemaining = $this->sanitize($attackerRemaining, true);
        $this->defenderRemaining = $this->sanitize($defenderRemaining, true);
    }

    public function getRound(): int
    {
        return $this->round;
    }

    /**
     * @return array<string, int>
     */
    public function getAttackerLosses(): array
    {
        return $this->attackerLosses;
    }

    /**
     * @return array<string, int>
     */
    public function getDefenderLosses(): array
    {
        return $this->defenderLosses;
    }

    /**
     * @return array<string, int>
     */
    public function getAttackerRemaining(): array
    {
        return $this->attackerRemaining;
    }

    /**
     * @return array<string, int>
     */
    public function getDefenderRemaining(): array
    {
        return $this->defenderRemaining;
    }

    /**
     * @param array<string, int|float> $values
     *
     * @return array<string, int>
     */
    private function sanitize(array $values, bool $allowZero = false): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $intValue = (int) $value;

            if (!$allowZero && $intValue <= 0) {
                continue;
            }

            if ($allowZero && $intValue < 0) {
                $intValue = 0;
            }

            $sanitized[$key] = $intValue;
        }

        ksort($sanitized);

        return $sanitized;
    }
}
