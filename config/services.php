<?php

declare(strict_types=1);

use App\Application\Service\ProcessBuildQueue;
use App\Application\Service\ProcessResearchQueue;
use App\Application\Service\Queue\QueueFinalizer;
use App\Application\Service\ProcessShipBuildQueue;
use App\Application\UseCase\Auth\LoginUser;
use App\Application\UseCase\Auth\LogoutUser;
use App\Application\UseCase\Auth\RegisterUser;
use App\Application\UseCase\Building\GetBuildingsOverview;
use App\Application\UseCase\Building\UpgradeBuilding;
use App\Application\UseCase\Dashboard\GetDashboard;
use App\Application\UseCase\Fleet\LaunchFleetMission;
use App\Application\UseCase\Fleet\PlanFleetMission;
use App\Application\UseCase\Fleet\ProcessFleetArrivals;
use App\Application\UseCase\Galaxy\GetGalaxyOverview;
use App\Application\UseCase\Journal\GetJournalOverview;
use App\Application\UseCase\Profile\GetProfileOverview;
use App\Application\UseCase\Research\GetResearchOverview;
use App\Application\UseCase\Research\GetTechTree;
use App\Application\UseCase\Research\StartResearch;
use App\Application\UseCase\Resource\GetResourceSnapshot;
use App\Application\UseCase\Shipyard\BuildShips;
use App\Application\UseCase\Shipyard\GetShipyardOverview;
use App\Controller\AuthController;
use App\Controller\ChangeLogController;
use App\Controller\ColonyController;
use App\Controller\DashboardController;
use App\Controller\FleetController;
use App\Controller\FleetMissionController;
use App\Controller\GalaxyController;
use App\Controller\JournalController;
use App\Controller\ProfileController;
use App\Controller\ResearchController;
use App\Controller\ResourceApiController;
use App\Controller\ShipyardController;
use App\Controller\TechTreeController;
use App\Domain\Config\BalanceConfig;
use App\Domain\Repository\BuildingStateRepositoryInterface;
use App\Domain\Repository\BuildQueueRepositoryInterface;
use App\Domain\Repository\FleetMovementRepositoryInterface;
use App\Domain\Repository\FleetRepositoryInterface;
use App\Domain\Repository\PlanetRepositoryInterface;
use App\Domain\Repository\PlayerStatsRepositoryInterface;
use App\Domain\Repository\ResearchQueueRepositoryInterface;
use App\Domain\Repository\ResearchStateRepositoryInterface;
use App\Domain\Repository\ShipBuildQueueRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\BuildingCalculator;
use App\Domain\Service\BuildingCatalog;
use App\Domain\Service\CostService;
use App\Domain\Service\FleetNavigationService;
use App\Domain\Service\ResearchCalculator;
use App\Domain\Service\ResearchCatalog;
use App\Domain\Service\ResourceEffectFactory;
use App\Domain\Service\ResourceTickService;
use App\Domain\Service\ShipCatalog;
use App\Infrastructure\Config\BalanceConfigLoader;
use App\Infrastructure\Config\BalanceGlobals;
use App\Infrastructure\Container\Container;
use App\Infrastructure\Database\ConnectionFactory;
use App\Infrastructure\Http\Session\FlashBag;
use App\Infrastructure\Http\Session\PhpSession;
use App\Infrastructure\Http\Session\SessionInterface;
use App\Infrastructure\Http\ViewRenderer;
use App\Infrastructure\Persistence\PdoBuildingStateRepository;
use App\Infrastructure\Persistence\PdoBuildQueueRepository;
use App\Infrastructure\Persistence\PdoFleetMovementRepository;
use App\Infrastructure\Persistence\PdoFleetRepository;
use App\Infrastructure\Persistence\PdoPlanetRepository;
use App\Infrastructure\Persistence\PdoPlayerStatsRepository;
use App\Infrastructure\Persistence\PdoResearchQueueRepository;
use App\Infrastructure\Persistence\PdoResearchStateRepository;
use App\Infrastructure\Persistence\PdoShipBuildQueueRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use App\Infrastructure\Security\CsrfTokenManager;

return function (Container $container): void {
    $container->set(ConnectionFactory::class, function (Container $c) {
        return new ConnectionFactory(
            $c->getParameter('db.host'),
            $c->getParameter('db.port'),
            $c->getParameter('db.name'),
            $c->getParameter('db.user'),
            $c->getParameter('db.pass')
        );
    });

    $container->set(\PDO::class, function (Container $c) {
        return $c->get(ConnectionFactory::class)->create();
    });

    $container->set(SessionInterface::class, fn () => new PhpSession());
    $container->set(FlashBag::class, fn (Container $c) => new FlashBag($c->get(SessionInterface::class)));
    $container->set(CsrfTokenManager::class, fn (Container $c) => new CsrfTokenManager($c->get(SessionInterface::class)));

    $container->set(ViewRenderer::class, fn () => new ViewRenderer(__DIR__ . '/../templates'));

    $container->set(BalanceConfigLoader::class, fn () => new BalanceConfigLoader(__DIR__ . '/balance'));

    $container->set(BalanceConfig::class, fn (Container $c) => $c->get(BalanceConfigLoader::class)->getBalanceConfig());
    $container->set(BalanceGlobals::class, fn (Container $c) => $c->get(BalanceConfigLoader::class)->getGlobals());

    $container->set(BuildingCatalog::class, function (Container $c) {
        return new BuildingCatalog($c->get(BalanceConfigLoader::class)->getBuildingConfigs());
    });

    $container->set(BuildingCalculator::class, fn (Container $c) => new BuildingCalculator($c->get(BuildingCatalog::class)));

    $container->set(ResourceTickService::class, function (Container $c) {
        $loader = $c->get(BalanceConfigLoader::class);
        $effects = ResourceEffectFactory::fromBuildingConfig($loader->getBuildingConfigs());

        return new ResourceTickService($effects, $c->get(BalanceConfig::class));
    });
    $container->set(CostService::class, fn (Container $c) => new CostService($c->get(BalanceConfig::class)));
    $container->set(FleetNavigationService::class, fn () => new FleetNavigationService());

    $container->set(ResearchCatalog::class, function (Container $c) {
        return new ResearchCatalog($c->get(BalanceConfigLoader::class)->getTechnologyConfigs());
    });

    $container->set(ResearchCalculator::class, function (Container $c) {
        $loader = $c->get(BalanceConfigLoader::class);
        $researchLab = $loader->getBuildingConfig('research_lab');
        $bonusConfig = $researchLab->getResearchSpeedBonus();

        $bonusPerLevel = 0.0;
        $bonusMax = 0.0;

        if (array_key_exists('per_level', $bonusConfig)) {
            $bonusPerLevel = (float)$bonusConfig['per_level'];
        } elseif (array_key_exists('base', $bonusConfig)) {
            $bonusPerLevel = (float)$bonusConfig['base'];
        }

        if (array_key_exists('max', $bonusConfig)) {
            $bonusMax = (float)$bonusConfig['max'];
        }

        return new ResearchCalculator(max(0.0, $bonusPerLevel), max(0.0, $bonusMax));
    });

    $container->set(ShipCatalog::class, function (Container $c) {
        return new ShipCatalog($c->get(BalanceConfigLoader::class)->getShipConfigs());
    });

    $container->set(UserRepositoryInterface::class, fn (Container $c) => new PdoUserRepository($c->get(\PDO::class)));
    $container->set(PlanetRepositoryInterface::class, fn (Container $c) => new PdoPlanetRepository(
        $c->get(\PDO::class),
        $c->get(BalanceGlobals::class)
    ));
    $container->set(PlayerStatsRepositoryInterface::class, fn (Container $c) => new PdoPlayerStatsRepository($c->get(\PDO::class)));
    $container->set(BuildingStateRepositoryInterface::class, fn (Container $c) => new PdoBuildingStateRepository($c->get(\PDO::class)));
    $container->set(BuildQueueRepositoryInterface::class, fn (Container $c) => new PdoBuildQueueRepository($c->get(\PDO::class)));
    $container->set(ResearchQueueRepositoryInterface::class, fn (Container $c) => new PdoResearchQueueRepository($c->get(\PDO::class)));
    $container->set(ResearchStateRepositoryInterface::class, fn (Container $c) => new PdoResearchStateRepository($c->get(\PDO::class)));
    $container->set(FleetRepositoryInterface::class, fn (Container $c) => new PdoFleetRepository($c->get(\PDO::class)));
    $container->set(ProcessBuildQueue::class, fn (Container $c) => new ProcessBuildQueue(
        $c->get(BuildQueueRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingCatalog::class),
        $c->get(BuildingCalculator::class)
    ));
    $container->set(QueueFinalizer::class, fn () => new QueueFinalizer());

    $container->set(ProcessResearchQueue::class, fn (Container $c) => new ProcessResearchQueue(
        $c->get(ResearchQueueRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(QueueFinalizer::class)
    ));
    $container->set(ProcessShipBuildQueue::class, fn (Container $c) => new ProcessShipBuildQueue(
        $c->get(ShipBuildQueueRepositoryInterface::class),
        $c->get(FleetRepositoryInterface::class),
        $c->get(QueueFinalizer::class)
    ));
    $container->set(ShipBuildQueueRepositoryInterface::class, fn (Container $c) => new PdoShipBuildQueueRepository($c->get(\PDO::class)));
    $container->set(FleetMovementRepositoryInterface::class, fn (Container $c) => new PdoFleetMovementRepository($c->get(\PDO::class)));

    $container->set(PlanFleetMission::class, fn (Container $c) => new PlanFleetMission(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(FleetRepositoryInterface::class),
        $c->get(ShipCatalog::class),
        $c->get(FleetNavigationService::class)
    ));
    $container->set(LaunchFleetMission::class, fn (Container $c) => new LaunchFleetMission(
        $c->get(PlanFleetMission::class),
        $c->get(PlanetRepositoryInterface::class),
        $c->get(FleetMovementRepositoryInterface::class)
    ));
    $container->set(ProcessFleetArrivals::class, fn (Container $c) => new ProcessFleetArrivals(
        $c->get(FleetMovementRepositoryInterface::class)
    ));

    $container->set(RegisterUser::class, fn (Container $c) => new RegisterUser(
        $c->get(UserRepositoryInterface::class),
        $c->get(PlanetRepositoryInterface::class),
        $c->get(SessionInterface::class)
    ));
    $container->set(LoginUser::class, fn (Container $c) => new LoginUser(
        $c->get(UserRepositoryInterface::class),
        $c->get(SessionInterface::class)
    ));
    $container->set(LogoutUser::class, fn (Container $c) => new LogoutUser($c->get(SessionInterface::class)));

    $container->set(GetDashboard::class, fn (Container $c) => new GetDashboard(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(BuildQueueRepositoryInterface::class),
        $c->get(ResearchQueueRepositoryInterface::class),
        $c->get(ShipBuildQueueRepositoryInterface::class),
        $c->get(PlayerStatsRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(FleetRepositoryInterface::class),
        $c->get(BuildingCatalog::class),
        $c->get(ResearchCatalog::class),
        $c->get(ShipCatalog::class),
        $c->get(BuildingCalculator::class),
        $c->get(ProcessBuildQueue::class),
        $c->get(ProcessResearchQueue::class),
        $c->get(ProcessShipBuildQueue::class)
    ));
    $container->set(GetGalaxyOverview::class, fn (Container $c) => new GetGalaxyOverview(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(UserRepositoryInterface::class)
    ));
    $container->set(GetJournalOverview::class, fn (Container $c) => new GetJournalOverview(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(BuildQueueRepositoryInterface::class),
        $c->get(ResearchQueueRepositoryInterface::class),
        $c->get(ShipBuildQueueRepositoryInterface::class),
        $c->get(ProcessBuildQueue::class),
        $c->get(ProcessResearchQueue::class),
        $c->get(ProcessShipBuildQueue::class),
        $c->get(BuildingCatalog::class),
        $c->get(ResearchCatalog::class),
        $c->get(ShipCatalog::class)
    ));
    $container->set(GetProfileOverview::class, fn (Container $c) => new GetProfileOverview(
        $c->get(UserRepositoryInterface::class),
        $c->get(GetDashboard::class)
    ));
    $container->set(GetResourceSnapshot::class, fn (Container $c) => new GetResourceSnapshot(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(ProcessBuildQueue::class),
        $c->get(ProcessResearchQueue::class),
        $c->get(ProcessShipBuildQueue::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(ResourceTickService::class)
    ));

    $container->set(GetBuildingsOverview::class, fn (Container $c) => new GetBuildingsOverview(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(BuildQueueRepositoryInterface::class),
        $c->get(BuildingCatalog::class),
        $c->get(BuildingCalculator::class),
        $c->get(ProcessBuildQueue::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(ResearchCatalog::class)
    ));

    $container->set(UpgradeBuilding::class, fn (Container $c) => new UpgradeBuilding(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(BuildQueueRepositoryInterface::class),
        $c->get(PlayerStatsRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(BuildingCatalog::class),
        $c->get(BuildingCalculator::class)
    ));

    $container->set(GetResearchOverview::class, fn (Container $c) => new GetResearchOverview(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(ResearchQueueRepositoryInterface::class),
        $c->get(ResearchCatalog::class),
        $c->get(ResearchCalculator::class),
        $c->get(ProcessResearchQueue::class)
    ));

    $container->set(StartResearch::class, fn (Container $c) => new StartResearch(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(ResearchQueueRepositoryInterface::class),
        $c->get(PlayerStatsRepositoryInterface::class),
        $c->get(ResearchCatalog::class),
        $c->get(ResearchCalculator::class)
    ));

    $container->set(GetTechTree::class, fn (Container $c) => new GetTechTree(
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(ResearchCatalog::class),
        $c->get(BuildingCatalog::class),
        $c->get(ShipCatalog::class)
    ));

    $container->set(GetShipyardOverview::class, fn (Container $c) => new GetShipyardOverview(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(ShipBuildQueueRepositoryInterface::class),
        $c->get(FleetRepositoryInterface::class),
        $c->get(ShipCatalog::class),
        $c->get(ProcessShipBuildQueue::class),
        $c->get(BuildingCatalog::class),
        $c->get(BuildingCalculator::class)
    ));

    $container->set(BuildShips::class, fn (Container $c) => new BuildShips(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(ResearchStateRepositoryInterface::class),
        $c->get(ShipBuildQueueRepositoryInterface::class),
        $c->get(PlayerStatsRepositoryInterface::class),
        $c->get(BuildingCatalog::class),
        $c->get(BuildingCalculator::class),
        $c->get(ShipCatalog::class)
    ));

    $container->set(AuthController::class, fn (Container $c) => new AuthController(
        $c->get(RegisterUser::class),
        $c->get(LoginUser::class),
        $c->get(LogoutUser::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(DashboardController::class, fn (Container $c) => new DashboardController(
        $c->get(GetDashboard::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(GalaxyController::class, fn (Container $c) => new GalaxyController(
        $c->get(GetGalaxyOverview::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(ColonyController::class, fn (Container $c) => new ColonyController(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(GetBuildingsOverview::class),
        $c->get(UpgradeBuilding::class),
        $c->get(ProcessBuildQueue::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(ResearchController::class, fn (Container $c) => new ResearchController(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(GetResearchOverview::class),
        $c->get(StartResearch::class),
        $c->get(ProcessResearchQueue::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(ShipyardController::class, fn (Container $c) => new ShipyardController(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(GetShipyardOverview::class),
        $c->get(BuildShips::class),
        $c->get(ProcessShipBuildQueue::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(FleetController::class, fn (Container $c) => new FleetController(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(FleetRepositoryInterface::class),
        $c->get(FleetMovementRepositoryInterface::class),
        $c->get(ShipCatalog::class),
        $c->get(ProcessShipBuildQueue::class),
        $c->get(PlanFleetMission::class),
        $c->get(ProcessFleetArrivals::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(FleetMissionController::class, fn (Container $c) => new FleetMissionController(
        $c->get(PlanFleetMission::class),
        $c->get(LaunchFleetMission::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(JournalController::class, fn (Container $c) => new JournalController(
        $c->get(GetJournalOverview::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(ProfileController::class, fn (Container $c) => new ProfileController(
        $c->get(GetProfileOverview::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(ResourceApiController::class, fn (Container $c) => new ResourceApiController(
        $c->get(GetResourceSnapshot::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));

    $container->set(TechTreeController::class, fn (Container $c) => new TechTreeController(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(GetTechTree::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));


    $container->set(ChangeLogController::class, fn (Container $c) => new ChangeLogController(
        $c->get(PlanetRepositoryInterface::class),
        $c->get(BuildingStateRepositoryInterface::class),
        $c->get(ViewRenderer::class),
        $c->get(SessionInterface::class),
        $c->get(FlashBag::class),
        $c->get(CsrfTokenManager::class),
        $c->getParameter('app.base_url')
    ));


};
