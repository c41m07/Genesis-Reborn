<?php

declare(strict_types=1);

namespace App\Domain\Battle\DTO;

abstract class FleetParticipantDTO
{
    /**
     * @var array<string, int>
     */
    private array $composition;

    /**
     * @var array<string, float>
     */
    private array $modifiers;

    /**
     * @param array<string, int> $composition
     * @param array<string, float|int> $modifiers
     */
    public function __construct(array $composition, array $modifiers = [])
    {
        $this->composition = $this->sanitizeComposition($composition);
        $this->modifiers = $this->sanitizeModifiers($modifiers);
    }

    /**
     * @return array<string, int>
     */
    public function getComposition(): array
    {
        return $this->composition;
    }

    /**
     * @return array<string, float>
     */
    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    /**
     * @param array<string, int> $composition
     *
     * @return array<string, int>
     */
    private function sanitizeComposition(array $composition): array
    {
        $sanitized = [];

        foreach ($composition as $shipKey => $quantity) {
            $shipKey = (string) $shipKey;
            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            $sanitized[$shipKey] = $quantity;
        }

        ksort($sanitized);

        return $sanitized;
    }

    /**
     * @param array<string, float|int> $modifiers
     *
     * @return array<string, float>
     */
    private function sanitizeModifiers(array $modifiers): array
    {
        $sanitized = [];

        foreach ($modifiers as $key => $value) {
            $sanitized[(string) $key] = (float) $value;
        }

        return $sanitized;
    }
}
