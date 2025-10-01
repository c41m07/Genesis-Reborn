<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\UseCase\Fleet\LaunchFleetMission;
use App\Application\UseCase\Fleet\PlanFleetMission;
use App\Controller\FleetMissionController;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\Session;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class FleetMissionControllerTest extends TestCase
{
    public function testPlanEndpointReturnsJsonPayload(): void
    {
        $storage = ['user_id' => 42];
        $session = new Session($storage);
        $flashBag = new FlashBag($session);
        $csrf = new CsrfTokenManager($session);
        $csrfToken = $csrf->generateToken('fleet_plan_7');

        $planUseCase = $this->createMock(PlanFleetMission::class);
        $planUseCase->expects(self::once())
            ->method('execute')
            ->with(42, 7, ['fighter' => 3], ['galaxy' => 2, 'system' => 3, 'position' => 4], 0.8, 'transport')
            ->willReturn([
                'success' => true,
                'errors' => [],
                'mission' => 'transport',
                'composition' => ['fighter' => 3],
                'destination' => ['galaxy' => 2, 'system' => 3, 'position' => 4],
                'plan' => [
                    'distance' => 1000,
                    'speed' => 12,
                    'travel_time' => 3600,
                    'arrival_time' => '2025-10-05T10:00:00+00:00',
                    'fuel' => 50,
                ],
            ]);

        $launchUseCase = $this->createMock(LaunchFleetMission::class);
        $controller = new FleetMissionController(
            $planUseCase,
            $launchUseCase,
            new class () extends ViewRenderer {
                public function __construct()
                {
                    parent::__construct(__DIR__);
                }
            },
            $session,
            $flashBag,
            $csrf,
            'https://example.test'
        );

        $request = new Request(
            'POST',
            '/fleet/plan',
            [],
            [
                'originPlanetId' => 7,
                'csrf_token' => $csrfToken,
                'destination' => ['galaxy' => 2, 'system' => 3, 'position' => 4],
                'composition' => ['fighter' => 3],
                'speedFactor' => 80.0,
            ],
            $session,
            []
        );

        $response = $controller->plan($request);

        self::assertSame(200, $this->getStatusCode($response));
        $payload = json_decode($this->getContent($response), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('transport', $payload['mission']);
        self::assertSame(['fighter' => 3], $payload['composition']);
        self::assertSame(['galaxy' => 2, 'system' => 3, 'position' => 4], $payload['destination']);
    }

    public function testLaunchEndpointReturnsErrorsWhenCsrfInvalid(): void
    {
        $storage = ['user_id' => 7];
        $session = new Session($storage);
        $flashBag = new FlashBag($session);
        $csrf = new CsrfTokenManager($session);
        $csrf->generateToken('fleet_launch_5');

        $planUseCase = $this->createMock(PlanFleetMission::class);
        $launchUseCase = $this->createMock(LaunchFleetMission::class);

        $controller = new FleetMissionController(
            $planUseCase,
            $launchUseCase,
            new class () extends ViewRenderer {
                public function __construct()
                {
                    parent::__construct(__DIR__);
                }
            },
            $session,
            $flashBag,
            $csrf,
            'https://example.test'
        );

        $request = new Request(
            'POST',
            '/fleet/launch',
            [],
            [
                'originPlanetId' => 5,
                'csrf_token' => 'invalid-token',
                'composition' => [],
                'destination' => ['galaxy' => 1, 'system' => 1, 'position' => 1],
                'speedFactor' => 100,
            ],
            $session,
            []
        );

        $response = $controller->launch($request);
        self::assertSame(419, $this->getStatusCode($response));
    }

    private function getStatusCode(Response $response): int
    {
        $property = new ReflectionProperty(Response::class, 'statusCode');
        $property->setAccessible(true);

        return (int)$property->getValue($response);
    }

    private function getContent(Response $response): string
    {
        $property = new ReflectionProperty(Response::class, 'content');
        $property->setAccessible(true);

        return (string)$property->getValue($response);
    }
}
