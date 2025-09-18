<?php

namespace App\Domain\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

class FleetResolutionService
{
    /** @var callable|null */
    private $defaultPveResolver;

    /** @var callable|null */
    private $defaultExplorationResolver;

    public function __construct(?callable $pveResolver = null, ?callable $explorationResolver = null)
    {
        $this->defaultPveResolver = $pveResolver;
        $this->defaultExplorationResolver = $explorationResolver;
    }

    /**
     * @param array<int, array{
     *     id?: int,
     *     player_id?: int,
     *     mission_type: string,
     *     status: string,
     *     arrival_at?: DateTimeInterface|null,
     *     return_at?: DateTimeInterface|null,
     *     travel_time_seconds?: int,
     *     mission_payload?: array<string, mixed>,
     * }> $fleets
     *
     * @return array<int, array{
     *     mission_type: string,
     *     status: string,
     *     arrival_at: ?DateTimeImmutable,
     *     return_at: ?DateTimeImmutable,
     *     travel_time_seconds: int,
     *     mission_payload: array<string, mixed>,
     * }>
     */
    public function advance(
        array $fleets,
        DateTimeInterface $now,
        ?callable $pveResolver = null,
        ?callable $explorationResolver = null
    ): array {
        $nowImmutable = DateTimeImmutable::createFromInterface($now);
        $pveResolver = $pveResolver ?? $this->defaultPveResolver;
        $explorationResolver = $explorationResolver ?? $this->defaultExplorationResolver;

        foreach ($fleets as &$fleet) {
            $fleet = $this->normaliseFleet($fleet);

            if ($fleet['status'] === 'outbound' && $fleet['arrival_at'] !== null && $fleet['arrival_at'] <= $nowImmutable) {
                $fleet = $this->resolveArrival($fleet, $nowImmutable, $pveResolver, $explorationResolver);
            }

            if ($fleet['status'] === 'returning' && $fleet['return_at'] !== null && $fleet['return_at'] <= $nowImmutable) {
                $fleet['status'] = 'completed';
                $fleet['return_at'] = $nowImmutable;
            }
        }

        unset($fleet);

        return $fleets;
    }

    /**
     * @param array<string, mixed> $fleet
     *
     * @return array{
     *     mission_type: string,
     *     status: string,
     *     arrival_at: ?DateTimeImmutable,
     *     return_at: ?DateTimeImmutable,
     *     travel_time_seconds: int,
     *     mission_payload: array<string, mixed>,
     * }
     */
    private function normaliseFleet(array $fleet): array
    {
        $fleet['mission_type'] = (string) ($fleet['mission_type'] ?? 'idle');
        $fleet['status'] = (string) ($fleet['status'] ?? 'idle');
        $fleet['mission_payload'] = isset($fleet['mission_payload']) && is_array($fleet['mission_payload'])
            ? $fleet['mission_payload']
            : [];
        $fleet['travel_time_seconds'] = (int) ($fleet['travel_time_seconds'] ?? 0);

        $fleet['arrival_at'] = isset($fleet['arrival_at']) && $fleet['arrival_at'] instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($fleet['arrival_at'])
            : null;

        $fleet['return_at'] = isset($fleet['return_at']) && $fleet['return_at'] instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($fleet['return_at'])
            : null;

        return $fleet;
    }

    /**
     * @param array{
     *     mission_type: string,
     *     status: string,
     *     arrival_at: ?DateTimeImmutable,
     *     return_at: ?DateTimeImmutable,
     *     travel_time_seconds: int,
     *     mission_payload: array<string, mixed>,
     * } $fleet
     * @param callable|null $pveResolver
     * @param callable|null $explorationResolver
     *
     * @return array{
     *     mission_type: string,
     *     status: string,
     *     arrival_at: ?DateTimeImmutable,
     *     return_at: ?DateTimeImmutable,
     *     travel_time_seconds: int,
     *     mission_payload: array<string, mixed>,
     * }
     */
    private function resolveArrival(array $fleet, DateTimeImmutable $now, ?callable $pveResolver, ?callable $explorationResolver): array
    {
        $resolution = null;

        switch ($fleet['mission_type']) {
            case 'pve':
                if ($pveResolver !== null) {
                    $resolution = $pveResolver($fleet);
                }

                if ($resolution === null) {
                    $resolution = [
                        'status' => 'returning',
                        'payload' => ['result' => 'victory'],
                        'return_delay' => $fleet['travel_time_seconds'],
                    ];
                }

                break;
            case 'explore':
                if ($explorationResolver !== null) {
                    $resolution = $explorationResolver($fleet);
                }

                if ($resolution === null) {
                    $resolution = [
                        'status' => 'returning',
                        'payload' => ['discovery' => null],
                        'return_delay' => $fleet['travel_time_seconds'],
                    ];
                }

                break;
            default:
                $resolution = [
                    'status' => 'holding',
                    'payload' => [],
                    'return_delay' => null,
                ];
        }

        $fleet['arrival_at'] = $now;
        $fleet['status'] = (string) ($resolution['status'] ?? $fleet['status']);
        $payload = $fleet['mission_payload'];
        $payload['last_resolution'] = $resolution['payload'] ?? [];
        $fleet['mission_payload'] = $payload;

        if ($fleet['status'] === 'returning') {
            $delay = $resolution['return_delay'] ?? $fleet['travel_time_seconds'];
            $delay = max(0, (int) $delay);
            $fleet['return_at'] = $now->add(new DateInterval('PT' . $delay . 'S'));
        } elseif ($fleet['status'] === 'holding' || $fleet['status'] === 'failed') {
            $fleet['return_at'] = null;
        }

        return $fleet;
    }
}
