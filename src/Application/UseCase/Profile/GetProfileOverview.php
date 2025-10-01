<?php

declare(strict_types=1);

namespace App\Application\UseCase\Profile;

use App\Application\UseCase\Dashboard\GetDashboard;
use App\Domain\Entity\Planet;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use RuntimeException;

final class GetProfileOverview
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly GetDashboard $getDashboard
    ) {
    }

    /**
     * @return array{
     *     account: array{email: string, username: string},
     *     planets: array<int, Planet>,
     *     selectedPlanetId: ?int,
     *     dashboard: array<string, mixed>,
     *     activePlanetSummary: array<string, mixed>|null,
     *     facilityStatuses: array<string, bool>
     * }
     */
    public function execute(int $userId): array
    {
        $user = $this->users->find($userId);
        if (!$user instanceof User) {
            throw new RuntimeException('Utilisateur introuvable.');
        }

        $dashboard = $this->getDashboard->execute($userId);
        $planetSummaries = $dashboard['planets'] ?? [];
        $planetList = array_map(static fn (array $summary) => $summary['planet'], $planetSummaries);

        $selectedPlanetId = null;
        $activePlanetSummary = null;
        $facilityStatuses = [];

        if ($planetSummaries !== []) {
            $firstSummary = $planetSummaries[0];
            $planet = $firstSummary['planet'];
            if ($planet instanceof Planet) {
                $selectedPlanetId = $planet->getId();
                $activePlanetSummary = [
                    'planet' => $planet,
                    'resources' => [
                        'metal' => ['value' => $planet->getMetal(), 'perHour' => $firstSummary['production']['metal'] ?? $planet->getMetalPerHour()],
                        'crystal' => ['value' => $planet->getCrystal(), 'perHour' => $firstSummary['production']['crystal'] ?? $planet->getCrystalPerHour()],
                        'hydrogen' => ['value' => $planet->getHydrogen(), 'perHour' => $firstSummary['production']['hydrogen'] ?? $planet->getHydrogenPerHour()],
                        'energy' => ['value' => $planet->getEnergy(), 'perHour' => $firstSummary['production']['energy'] ?? $planet->getEnergyPerHour()],
                    ],
                ];
                $levels = $firstSummary['levels'] ?? [];
                $facilityStatuses = [
                    'research_lab' => ($levels['research_lab'] ?? 0) > 0,
                    'shipyard' => ($levels['shipyard'] ?? 0) > 0,
                ];
            }
        }

        return [
            'account' => [
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
            ],
            'planets' => $planetList,
            'selectedPlanetId' => $selectedPlanetId,
            'dashboard' => $dashboard,
            'activePlanetSummary' => $activePlanetSummary,
            'facilityStatuses' => $facilityStatuses,
        ];
    }
}
