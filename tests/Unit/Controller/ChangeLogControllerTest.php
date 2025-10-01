<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ChangeLogController;
use App\Domain\Entity\Planet;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\Session;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;

final class ChangeLogControllerTest extends TestCase
{
    public function testIndexLocksFacilitiesWhenBuildingsMissing(): void
    {
        $sessionStorage = ['user_id' => 42];
        $session = new Session($sessionStorage);
        $flashBag = new FlashBag($session);

        $planet = $this->createPlanet(7, 42);

        $renderer = $this->createMock(ViewRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with(
                'pages/changelog/index.php',
                $this->callback(function (array $params) use ($planet): bool {
                    $this->assertArrayHasKey('facilityStatuses', $params);
                    $this->assertSame([
                        'research_lab' => false,
                        'shipyard' => false,
                    ], $params['facilityStatuses']);
                    $this->assertSame([$planet], $params['planets']);
                    $this->assertSame($planet->getId(), $params['selectedPlanetId']);
                    $this->assertIsArray($params['activePlanetSummary']);
                    $this->assertSame($planet, $params['activePlanetSummary']['planet']);

                    return true;
                })
            )
            ->willReturn('rendered');

        $csrfManager = $this->createMock(CsrfTokenManager::class);
        $csrfManager->expects($this->once())
            ->method('generateToken')
            ->with('logout')
            ->willReturn('logout-token');

        $planets = $this->createMock(PlanetRepositoryInterface::class);
        $planets->expects($this->once())
            ->method('findByUser')
            ->with(42)
            ->willReturn([$planet]);

        $buildingStates = $this->createMock(BuildingStateRepositoryInterface::class);
        $buildingStates->expects($this->once())
            ->method('getLevels')
            ->with($planet->getId())
            ->willReturn([
                'research_lab' => 0,
                'shipyard' => 0,
            ]);

        $controller = new ChangeLogController(
            $planets,
            $buildingStates,
            $renderer,
            $session,
            $flashBag,
            $csrfManager,
            'https://example.test'
        );

        $request = new Request('GET', '/changelog', [], [], $session);

        $response = $controller->index($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('rendered', $response->getContent());
    }

    private function createPlanet(int $planetId, int $userId): Planet
    {
        return new Planet(
            $planetId,
            $userId,
            1,
            1,
            1,
            'Gaia',
            10000,
            -10,
            30,
            100,
            200,
            300,
            400,
            10,
            20,
            30,
            40,
            1000,
            1000,
            1000,
            1000
        );
    }
}
