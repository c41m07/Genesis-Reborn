<?php

namespace App\Domain\Battle\DTO;

final class FleetBattleResultDTO
{
    private string $winner;

    /**
     * @var array<string, int>
     */
    private array $attackerRemaining;

    /**
     * @var array<string, int>
     */
    private array $defenderRemaining;

    /**
     * @var list<FleetBattleRoundDTO>
     */
    private array $rounds;

    public function __construct(
        string $winner,
        array $attackerRemaining,
        array $defenderRemaining,
        array $rounds,
        private readonly bool $attackerRetreated,
        private readonly bool $defenderRetreated
    ) {
        $this->winner = $this->sanitizeWinner($winner);
        $this->attackerRemaining = $this->sanitizeRemaining($attackerRemaining);
        $this->defenderRemaining = $this->sanitizeRemaining($defenderRemaining);
        $this->rounds = $this->sanitizeRounds($rounds);
    }

    public function getWinner(): string
    {
        return $this->winner;
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
     * @return list<FleetBattleRoundDTO>
     */
    public function getRounds(): array
    {
        return $this->rounds;
    }

    public function getRoundsFought(): int
    {
        return count($this->rounds);
    }

    public function didAttackerRetreat(): bool
    {
        return $this->attackerRetreated;
    }

    public function didDefenderRetreat(): bool
    {
        return $this->defenderRetreated;
    }

    private function sanitizeWinner(string $winner): string
    {
        $normalized = strtolower($winner);
        $allowed = ['attacker', 'defender', 'draw'];

        if (!in_array($normalized, $allowed, true)) {
            return 'draw';
        }

        return $normalized;
    }

    /**
     * @param array<string, int|float> $values
     *
     * @return array<string, int>
     */
    private function sanitizeRemaining(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $intValue = (int) $value;
            if ($intValue < 0) {
                $intValue = 0;
            }

            $sanitized[$key] = $intValue;
        }

        ksort($sanitized);

        return $sanitized;
    }

    /**
     * @param array<int, mixed> $rounds
     *
     * @return list<FleetBattleRoundDTO>
     */
    private function sanitizeRounds(array $rounds): array
    {
        $sanitized = [];

        foreach ($rounds as $round) {
            if ($round instanceof FleetBattleRoundDTO) {
                $sanitized[] = $round;
            }
        }

        return $sanitized;
    }
}
